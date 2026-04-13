<?php

namespace App\Exports;

use App\Models\HdPermission;
use App\Models\HdTicket;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HelpdeskTicketsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(
        protected User $user,
        protected array $filters = [],
    ) {}

    public function query()
    {
        $query = HdTicket::query()
            ->with(['requester', 'assignedTechnician', 'department', 'category'])
            ->latest();

        // Visibility scope (mirror HelpdeskService::getTicketsForUser)
        $userDeptIds = HdPermission::where('user_id', $this->user->id)
            ->pluck('department_id')->toArray();

        if (! empty($userDeptIds)) {
            $query->where(function ($q) use ($userDeptIds) {
                $q->where('requester_id', $this->user->id)
                    ->orWhereIn('department_id', $userDeptIds);
            });
        } else {
            $query->where('requester_id', $this->user->id);
        }

        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }
        if (! empty($this->filters['priority'])) {
            $query->where('priority', (int) $this->filters['priority']);
        }
        if (! empty($this->filters['department_id'])) {
            $query->where('department_id', (int) $this->filters['department_id']);
        }
        if (! empty($this->filters['assigned_to_me'])) {
            $query->where('assigned_technician_id', $this->user->id);
        }
        if (! empty($this->filters['search'])) {
            $s = $this->filters['search'];
            $query->where(fn ($q) => $q->where('title', 'like', "%{$s}%")->orWhere('id', $s));
        }
        if (! empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (! empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            '#',
            'Título',
            'Solicitante',
            'Departamento',
            'Categoria',
            'Técnico',
            'Status',
            'Prioridade',
            'SLA',
            'SLA Vencido?',
            'Criado em',
            'Resolvido em',
            'Fechado em',
        ];
    }

    public function map($ticket): array
    {
        return [
            $ticket->id,
            $ticket->title,
            $ticket->requester?->name ?? '-',
            $ticket->department?->name ?? '-',
            $ticket->category?->name ?? '-',
            $ticket->assignedTechnician?->name ?? 'Não atribuído',
            HdTicket::STATUS_LABELS[$ticket->status] ?? $ticket->status,
            HdTicket::PRIORITY_LABELS[$ticket->priority] ?? $ticket->priority,
            $ticket->sla_due_at?->format('d/m/Y H:i') ?? '-',
            $ticket->is_overdue ? 'SIM' : 'Não',
            $ticket->created_at->format('d/m/Y H:i'),
            $ticket->resolved_at?->format('d/m/Y H:i') ?? '-',
            $ticket->closed_at?->format('d/m/Y H:i') ?? '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
