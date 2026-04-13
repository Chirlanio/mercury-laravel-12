<?php

namespace App\Services;

use App\Models\HdSatisfactionSurvey;
use App\Models\HdTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HelpdeskReportService
{
    /**
     * Volume of tickets by day.
     */
    public function volumeByDay(array $filters = []): array
    {
        if (! Schema::hasTable('hd_tickets')) {
            return [];
        }

        $query = HdTicket::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date');

        if (! empty($filters['department_id'])) {
            $query->forDepartment((int) $filters['department_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->get()->map(fn ($row) => [
            'date' => $row->date,
            'count' => $row->count,
        ])->toArray();
    }

    /**
     * SLA compliance percentage.
     */
    public function slaCompliance(array $filters = []): array
    {
        if (! Schema::hasTable('hd_tickets')) {
            return ['total' => 0, 'within_sla' => 0, 'breached' => 0, 'compliance_rate' => 0];
        }

        $query = HdTicket::whereNotNull('sla_due_at')
            ->whereIn('status', [HdTicket::STATUS_RESOLVED, HdTicket::STATUS_CLOSED]);

        if (! empty($filters['department_id'])) {
            $query->forDepartment((int) $filters['department_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $total = (clone $query)->count();
        $withinSla = (clone $query)->whereColumn('resolved_at', '<=', 'sla_due_at')->count();
        $breached = $total - $withinSla;

        return [
            'total' => $total,
            'within_sla' => $withinSla,
            'breached' => $breached,
            'compliance_rate' => $total > 0 ? round(($withinSla / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Distribution by department.
     */
    public function distributionByDepartment(array $filters = []): array
    {
        if (! Schema::hasTable('hd_tickets')) {
            return [];
        }

        $query = HdTicket::select('department_id', DB::raw('COUNT(*) as count'))
            ->with('department:id,name')
            ->groupBy('department_id');

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->get()->map(fn ($row) => [
            'department' => $row->department?->name ?? 'N/A',
            'count' => $row->count,
        ])->toArray();
    }

    /**
     * Average resolution time in hours.
     */
    public function averageResolutionTime(array $filters = []): float
    {
        if (! Schema::hasTable('hd_tickets')) {
            return 0;
        }

        $query = HdTicket::whereNotNull('resolved_at');

        if (! empty($filters['department_id'])) {
            $query->forDepartment((int) $filters['department_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Portable across MySQL/MariaDB/SQLite/Postgres: compute in PHP.
        // Reports are aggregate views — dataset is small, no hot path here.
        $tickets = $query->get(['created_at', 'resolved_at']);
        if ($tickets->isEmpty()) {
            return 0;
        }

        $totalHours = $tickets->sum(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at) / 60);

        return round($totalHours / $tickets->count(), 1);
    }

    // ------------------------------------------------------------------
    // CSAT — satisfaction surveys
    // ------------------------------------------------------------------

    /**
     * Overall CSAT: average rating across all submitted surveys within
     * the filter window plus a breakdown by star count so the UI can
     * render a distribution bar chart.
     *
     * Returns:
     *   [
     *     'total_submitted' => int,   // surveys with a rating
     *     'total_sent'      => int,   // surveys that exist at all in window
     *     'response_rate'   => float, // percentage
     *     'average'         => float, // 0..5 (0 if nothing submitted)
     *     'distribution'    => [1=>n, 2=>n, 3=>n, 4=>n, 5=>n],
     *     'nps_like'        => [
     *         'promoters'  => int,  // 5 stars
     *         'passives'   => int,  // 4 stars
     *         'detractors' => int,  // 1-3 stars
     *     ],
     *   ]
     */
    public function csatOverview(array $filters = []): array
    {
        if (! Schema::hasTable('hd_satisfaction_surveys')) {
            return $this->emptyCsatOverview();
        }

        $query = $this->baseCsatQuery($filters);

        $totalSent = (clone $query)->count();
        $submitted = (clone $query)->whereNotNull('submitted_at')->get(['rating']);
        $totalSubmitted = $submitted->count();

        if ($totalSubmitted === 0) {
            return array_merge($this->emptyCsatOverview(), ['total_sent' => $totalSent]);
        }

        $sum = $submitted->sum('rating');
        $average = round($sum / $totalSubmitted, 2);

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($submitted as $s) {
            $distribution[$s->rating] = ($distribution[$s->rating] ?? 0) + 1;
        }

        return [
            'total_submitted' => $totalSubmitted,
            'total_sent' => $totalSent,
            'response_rate' => $totalSent > 0 ? round(($totalSubmitted / $totalSent) * 100, 1) : 0.0,
            'average' => $average,
            'distribution' => $distribution,
            'nps_like' => [
                'promoters' => $distribution[5] ?? 0,
                'passives' => $distribution[4] ?? 0,
                'detractors' => ($distribution[1] ?? 0) + ($distribution[2] ?? 0) + ($distribution[3] ?? 0),
            ],
        ];
    }

    /**
     * Top technicians by average CSAT. Only includes technicians with
     * at least $minResponses submitted surveys so one lucky 5-star
     * doesn't dominate the ranking.
     *
     * @return array<int, array{user_id:int, user_name:?string, average:float, total:int}>
     */
    public function csatByTechnician(array $filters = [], int $minResponses = 1, int $limit = 10): array
    {
        if (! Schema::hasTable('hd_satisfaction_surveys')) {
            return [];
        }

        $query = $this->baseCsatQuery($filters)
            ->whereNotNull('submitted_at')
            ->whereNotNull('resolved_by_user_id')
            ->with('resolvedBy:id,name');

        $grouped = $query->get(['resolved_by_user_id', 'rating'])
            ->groupBy('resolved_by_user_id');

        $rows = [];
        foreach ($grouped as $userId => $surveys) {
            $total = $surveys->count();
            if ($total < $minResponses) {
                continue;
            }
            $avg = round($surveys->sum('rating') / $total, 2);
            $rows[] = [
                'user_id' => (int) $userId,
                'user_name' => $surveys->first()->resolvedBy?->name,
                'average' => $avg,
                'total' => $total,
            ];
        }

        usort($rows, fn ($a, $b) => [$b['average'], $b['total']] <=> [$a['average'], $a['total']]);

        return array_slice($rows, 0, $limit);
    }

    /**
     * Average CSAT per department.
     *
     * @return array<int, array{department_id:int, department_name:string, average:float, total:int}>
     */
    public function csatByDepartment(array $filters = []): array
    {
        if (! Schema::hasTable('hd_satisfaction_surveys')) {
            return [];
        }

        $surveys = $this->baseCsatQuery($filters)
            ->whereNotNull('submitted_at')
            ->whereNotNull('department_id')
            ->with('department:id,name')
            ->get(['department_id', 'rating']);

        $rows = [];
        foreach ($surveys->groupBy('department_id') as $deptId => $deptSurveys) {
            $total = $deptSurveys->count();
            $rows[] = [
                'department_id' => (int) $deptId,
                'department_name' => $deptSurveys->first()->department?->name ?? 'N/A',
                'average' => round($deptSurveys->sum('rating') / $total, 2),
                'total' => $total,
            ];
        }

        usort($rows, fn ($a, $b) => $b['average'] <=> $a['average']);

        return $rows;
    }

    /**
     * Build the base query that applies the shared date/department filters
     * to all CSAT reports. Uses sent_at as the anchor (the time the survey
     * was created, which is right after the ticket was resolved).
     */
    protected function baseCsatQuery(array $filters)
    {
        $query = HdSatisfactionSurvey::query();

        if (! empty($filters['department_id'])) {
            $query->where('department_id', (int) $filters['department_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    protected function emptyCsatOverview(): array
    {
        return [
            'total_submitted' => 0,
            'total_sent' => 0,
            'response_rate' => 0.0,
            'average' => 0.0,
            'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'nps_like' => ['promoters' => 0, 'passives' => 0, 'detractors' => 0],
        ];
    }
}
