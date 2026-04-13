<?php

namespace App\Services;

use App\Models\HdBusinessHour;
use App\Models\HdDepartment;
use App\Models\HdHoliday;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

/**
 * Computes SLA due dates respecting business hours and holidays.
 *
 * The algorithm walks forward from the ticket creation time, consuming the
 * SLA budget only during business windows and skipping holidays. It snaps
 * the starting cursor to the next business window when the ticket is created
 * outside working hours (e.g. Friday 22h → Monday 08h).
 *
 * Fallbacks: if config('helpdesk.sla_mode') is 'calendar' or the schedule
 * tables are missing (SQLite tests), it reverts to naive wall-clock hours.
 *
 * Caching is in-memory on the instance (not Laravel's Cache facade). This
 * avoids stancl/tenancy's tagged-cache requirement and keeps results scoped
 * to the current tenant automatically — each tenant run gets a fresh service
 * instance from the container.
 */
class HelpdeskSlaCalculator
{
    /** @var array<string, array<int, array<int, array{0:string,1:string}>>> */
    private array $scheduleCache = [];

    /** @var array<string, array<int, string>> */
    private array $holidayCache = [];

    /**
     * Compute the SLA due date for a ticket.
     */
    public function calculateDueDate(Carbon $createdAt, int $slaHours, ?HdDepartment $department = null): Carbon
    {
        if ($slaHours <= 0) {
            return $createdAt->copy();
        }

        if (config('helpdesk.sla_mode', 'business') !== 'business' || ! $this->schemaReady()) {
            return $createdAt->copy()->addHours($slaHours);
        }

        $tz = config('helpdesk.timezone') ?: config('app.timezone');
        $cursor = CarbonImmutable::parse($createdAt)->setTimezone($tz);
        $budgetMinutes = $slaHours * 60;

        $schedule = $this->loadSchedule($department?->id);
        $holidays = $this->loadHolidays($department?->id);

        // Safety limit: SLA walks at most one year of calendar days.
        for ($i = 0; $i < 370 && $budgetMinutes > 0; $i++) {
            // Skip holidays entirely.
            if (in_array($cursor->toDateString(), $holidays, true)) {
                $cursor = $cursor->addDay()->startOfDay();

                continue;
            }

            $ranges = $this->rangesForDate($cursor, $schedule);

            if (empty($ranges)) {
                $cursor = $cursor->addDay()->startOfDay();

                continue;
            }

            foreach ($ranges as [$rangeStart, $rangeEnd]) {
                // Cursor already past this window — move on.
                if ($cursor->greaterThanOrEqualTo($rangeEnd)) {
                    continue;
                }

                // Cursor is before window opens — snap forward.
                if ($cursor->lessThan($rangeStart)) {
                    $cursor = $rangeStart;
                }

                $availableMinutes = $cursor->diffInMinutes($rangeEnd, false);

                if ($availableMinutes <= 0) {
                    continue;
                }

                if ($budgetMinutes <= $availableMinutes) {
                    return Carbon::instance($cursor->addMinutes($budgetMinutes)->toDateTime());
                }

                $budgetMinutes -= $availableMinutes;
                $cursor = $rangeEnd;
            }

            // Exhausted today's windows — jump to tomorrow.
            $cursor = $cursor->addDay()->startOfDay();
        }

        // Defensive fallback — should not normally be reached.
        return $createdAt->copy()->addHours($slaHours);
    }

    /**
     * Memoized load of a department's schedule. Falls back to the global (null
     * department) schedule in DB, then to config('helpdesk.business_hours.default').
     *
     * @return array<int, array<int, array{0:string,1:string}>>  weekday => [[start,end],...]
     */
    public function loadSchedule(?int $departmentId): array
    {
        $key = (string) ($departmentId ?? 'global');

        if (isset($this->scheduleCache[$key])) {
            return $this->scheduleCache[$key];
        }

        $rows = HdBusinessHour::query()
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when(! $departmentId, fn ($q) => $q->whereNull('department_id'))
            ->get();

        if ($rows->isEmpty() && $departmentId !== null) {
            // Fall back to global schedule stored in DB.
            $rows = HdBusinessHour::query()->whereNull('department_id')->get();
        }

        if ($rows->isEmpty()) {
            return $this->scheduleCache[$key] = config('helpdesk.business_hours.default', []);
        }

        $schedule = [];
        foreach ($rows as $row) {
            $schedule[$row->weekday][] = [
                substr($row->start_time, 0, 5),
                substr($row->end_time, 0, 5),
            ];
        }

        foreach ($schedule as &$ranges) {
            usort($ranges, fn ($a, $b) => strcmp($a[0], $b[0]));
        }
        unset($ranges);

        return $this->scheduleCache[$key] = $schedule;
    }

    /**
     * @return array<int, string>  list of Y-m-d strings
     */
    public function loadHolidays(?int $departmentId): array
    {
        $key = (string) ($departmentId ?? 'global');

        if (isset($this->holidayCache[$key])) {
            return $this->holidayCache[$key];
        }

        return $this->holidayCache[$key] = HdHoliday::query()
            ->when($departmentId, fn ($q) => $q->where(function ($q2) use ($departmentId) {
                $q2->where('department_id', $departmentId)->orWhereNull('department_id');
            }))
            ->when(! $departmentId, fn ($q) => $q->whereNull('department_id'))
            ->pluck('date')
            ->map(fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) $d)
            ->values()
            ->all();
    }

    /**
     * Convert a schedule entry for a given date into absolute CarbonImmutable ranges.
     *
     * @param  array<int, array<int, array{0:string,1:string}>>  $schedule
     * @return array<int, array{0:CarbonImmutable,1:CarbonImmutable}>
     */
    private function rangesForDate(CarbonImmutable $date, array $schedule): array
    {
        // Carbon's dayOfWeek uses 0=Sun..6=Sat; we use ISO 1=Mon..7=Sun.
        $isoWeekday = $date->isoWeekday();
        $ranges = $schedule[$isoWeekday] ?? [];

        $result = [];
        foreach ($ranges as [$startStr, $endStr]) {
            [$sh, $sm] = explode(':', $startStr);
            [$eh, $em] = explode(':', $endStr);
            $start = $date->setTime((int) $sh, (int) $sm, 0);
            $end = $date->setTime((int) $eh, (int) $em, 0);
            if ($end->greaterThan($start)) {
                $result[] = [$start, $end];
            }
        }

        return $result;
    }

    private function schemaReady(): bool
    {
        return Schema::hasTable('hd_business_hours') && Schema::hasTable('hd_holidays');
    }

    /**
     * Clear in-memory caches. Useful in long-running processes (queue workers)
     * after admin edits to business hours / holidays, or between tenants when
     * the same service instance is reused.
     */
    public function flushCache(?int $departmentId = null): void
    {
        if ($departmentId === null) {
            $this->scheduleCache = [];
            $this->holidayCache = [];

            return;
        }

        $key = (string) $departmentId;
        unset($this->scheduleCache[$key], $this->holidayCache[$key]);
    }
}
