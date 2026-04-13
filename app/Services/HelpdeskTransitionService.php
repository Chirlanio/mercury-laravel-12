<?php

namespace App\Services;

use App\Events\Helpdesk\TicketAssignedEvent;
use App\Models\HdInteraction;
use App\Models\HdTicket;
use Illuminate\Support\Facades\DB;

class HelpdeskTransitionService
{
    public function __construct(
        private ?HelpdeskSlaCalculator $slaCalculator = null,
    ) {
        $this->slaCalculator ??= app(HelpdeskSlaCalculator::class);
    }

    /**
     * Validate if a transition is allowed.
     */
    public function validateTransition(HdTicket $ticket, string $newStatus): array
    {
        $errors = [];

        if (! $ticket->canTransitionTo($newStatus)) {
            $errors[] = "Transição de '{$ticket->status_label}' para '".HdTicket::STATUS_LABELS[$newStatus]."' não permitida.";
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Execute a status transition.
     */
    public function executeTransition(HdTicket $ticket, string $newStatus, int $userId, ?string $notes = null): void
    {
        DB::transaction(function () use ($ticket, $newStatus, $userId, $notes) {
            $oldStatus = $ticket->status;

            $updateData = [
                'status' => $newStatus,
                'updated_by_user_id' => $userId,
            ];

            if ($newStatus === HdTicket::STATUS_RESOLVED) {
                $updateData['resolved_at'] = now();
            }
            if ($newStatus === HdTicket::STATUS_CLOSED) {
                $updateData['closed_at'] = now();
            }

            $ticket->update($updateData);

            HdInteraction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'comment' => $notes,
                'type' => 'status_change',
                'old_value' => $oldStatus,
                'new_value' => $newStatus,
            ]);
        });
    }

    /**
     * Assign a technician to a ticket.
     */
    public function assignTechnician(HdTicket $ticket, int $technicianId, int $assignedBy): void
    {
        DB::transaction(function () use ($ticket, $technicianId, $assignedBy) {
            $oldTechnicianId = $ticket->assigned_technician_id;

            $ticket->update([
                'assigned_technician_id' => $technicianId,
                'updated_by_user_id' => $assignedBy,
            ]);

            // Auto-transition to in_progress if still open
            if ($ticket->status === HdTicket::STATUS_OPEN) {
                $ticket->update(['status' => HdTicket::STATUS_IN_PROGRESS]);
            }

            $oldName = $oldTechnicianId ? \App\Models\User::find($oldTechnicianId)?->name : null;
            $newName = \App\Models\User::find($technicianId)?->name;

            HdInteraction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $assignedBy,
                'comment' => $oldName
                    ? "Chamado reatribuído de {$oldName} para {$newName}."
                    : "Chamado atribuído a {$newName}.",
                'type' => 'assignment',
                'old_value' => $oldName,
                'new_value' => $newName,
            ]);

            try {
                TicketAssignedEvent::dispatch(
                    $ticket->id,
                    $ticket->department_id,
                    $technicianId,
                    $oldTechnicianId,
                );
            } catch (\Throwable $e) {
                // Broadcast indisponível — ignora
            }
        });
    }

    /**
     * Change ticket priority.
     */
    public function changePriority(HdTicket $ticket, int $newPriority, int $changedBy): void
    {
        DB::transaction(function () use ($ticket, $newPriority, $changedBy) {
            $oldPriority = $ticket->priority;

            // Recalculate SLA based on new priority and the ticket's department schedule.
            // Anchored to created_at (not "now") so a tightened SLA can retroactively
            // move the due date earlier rather than reward slow priority changes.
            $slaHours = HdTicket::SLA_HOURS[$newPriority] ?? 48;
            $newDueAt = $this->slaCalculator->calculateDueDate(
                $ticket->created_at->copy(),
                $slaHours,
                $ticket->department,
            );

            $ticket->update([
                'priority' => $newPriority,
                'sla_due_at' => $newDueAt,
                'updated_by_user_id' => $changedBy,
            ]);

            HdInteraction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $changedBy,
                'comment' => 'Prioridade alterada.',
                'type' => 'priority_change',
                'old_value' => HdTicket::PRIORITY_LABELS[$oldPriority] ?? $oldPriority,
                'new_value' => HdTicket::PRIORITY_LABELS[$newPriority] ?? $newPriority,
            ]);
        });
    }
}
