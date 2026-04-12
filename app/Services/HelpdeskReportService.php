<?php

namespace App\Services;

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

        $avg = $query->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
            ->value('avg_hours');

        return round($avg ?? 0, 1);
    }
}
