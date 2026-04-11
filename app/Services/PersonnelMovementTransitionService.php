<?php

namespace App\Services;

use App\Models\PersonnelMovement;
use App\Models\PersonnelMovementStatusHistory;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;

class PersonnelMovementTransitionService
{
    public function validateTransition(PersonnelMovement $movement, string $newStatus, array $data = []): array
    {
        $errors = [];

        if (! $movement->canTransitionTo($newStatus)) {
            $errors[] = "Transição de '{$movement->status_label}' para '".(PersonnelMovement::STATUS_LABELS[$newStatus] ?? $newStatus)."' não é permitida.";
        }

        if ($newStatus === PersonnelMovement::STATUS_CANCELLED && empty($data['notes'])) {
            $errors[] = 'Motivo do cancelamento é obrigatório.';
        }

        if ($newStatus === PersonnelMovement::STATUS_COMPLETED && $movement->type === PersonnelMovement::TYPE_DISMISSAL) {
            if (! $movement->followUp) {
                $errors[] = 'Checklist de follow-up deve ser preenchido antes de concluir o desligamento.';
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function executeTransition(PersonnelMovement $movement, string $newStatus, array $data, int $userId): void
    {
        DB::transaction(function () use ($movement, $newStatus, $data, $userId) {
            $oldStatus = $movement->status;

            $updateData = [
                'status' => $newStatus,
                'updated_by_user_id' => $userId,
            ];

            $movement->update($updateData);

            if ($newStatus === PersonnelMovement::STATUS_COMPLETED) {
                $this->executeCompletionSideEffects($movement);
            }

            $this->recordHistory(
                $movement->id,
                $oldStatus,
                $newStatus,
                $userId,
                $data['notes'] ?? null
            );
        });
    }

    private function executeCompletionSideEffects(PersonnelMovement $movement): void
    {
        match ($movement->type) {
            PersonnelMovement::TYPE_DISMISSAL => $this->completeDismissal($movement),
            PersonnelMovement::TYPE_PROMOTION => $this->completePromotion($movement),
            PersonnelMovement::TYPE_TRANSFER => $this->completeTransfer($movement),
            PersonnelMovement::TYPE_REACTIVATION => $this->completeReactivation($movement),
        };
    }

    private function completeDismissal(PersonnelMovement $movement): void
    {
        $employee = $movement->employee;
        $effectiveDate = $movement->last_day_worked ?? $movement->effective_date;

        // Inactivate employee
        $employee->update([
            'dismissal_date' => $effectiveDate,
            'status_id' => $this->getInactiveStatusId(),
        ]);

        // Cancel pending vacations
        if (class_exists(Vacation::class)) {
            Vacation::where('employee_id', $employee->id)
                ->whereIn('status', ['draft', 'pending_manager', 'approved_manager', 'approved_rh'])
                ->update(['status' => 'cancelled']);
        }
    }

    private function completePromotion(PersonnelMovement $movement): void
    {
        if ($movement->new_position_id) {
            $movement->employee->update([
                'position_id' => $movement->new_position_id,
            ]);
        }
    }

    private function completeTransfer(PersonnelMovement $movement): void
    {
        if ($movement->destination_store_id) {
            $movement->employee->update([
                'store_id' => $movement->destination_store_id,
            ]);
        }
    }

    private function completeReactivation(PersonnelMovement $movement): void
    {
        $movement->employee->update([
            'dismissal_date' => null,
            'status_id' => $this->getActiveStatusId(),
        ]);

        if ($movement->new_position_id) {
            $movement->employee->update([
                'position_id' => $movement->new_position_id,
            ]);
        }
    }

    public function recordHistory(int $movementId, ?string $oldStatus, string $newStatus, int $userId, ?string $notes = null): void
    {
        PersonnelMovementStatusHistory::create([
            'personnel_movement_id' => $movementId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by_user_id' => $userId,
            'notes' => $notes,
        ]);
    }

    private function getInactiveStatusId(): int
    {
        return \App\Models\EmployeeStatus::where('name', 'like', '%Inativo%')
            ->orWhere('name', 'like', '%Desligad%')
            ->value('id') ?? 3;
    }

    private function getActiveStatusId(): int
    {
        return \App\Models\EmployeeStatus::where('name', 'like', '%Ativo%')
            ->value('id') ?? 2;
    }
}
