<?php

namespace App\Services;

use App\Models\Vacation;
use App\Models\VacationLog;
use App\Models\VacationPeriod;
use Illuminate\Support\Facades\DB;

class VacationTransitionService
{
    /**
     * Permissões por transição (role mínima).
     * SUPER_ADMIN e ADMIN podem tudo. SUPPORT pode aprovar como gestor.
     */
    private const TRANSITION_ROLES = [
        'draft->pending_manager' => 'user',           // Qualquer usuário
        'draft->cancelled' => 'user',                  // Qualquer usuário
        'pending_manager->approved_manager' => 'support', // Suporte+ (gestor)
        'pending_manager->cancelled' => 'support',
        'pending_manager->rejected_manager' => 'support',
        'approved_manager->approved_rh' => 'admin',    // Admin+ (RH)
        'approved_manager->cancelled' => 'admin',
        'approved_manager->rejected_rh' => 'admin',
        'approved_rh->in_progress' => 'admin',         // Admin+ (RH)
        'approved_rh->cancelled' => 'admin',
        'in_progress->completed' => 'admin',           // Admin+ (RH)
        'rejected_manager->draft' => 'user',           // Voltar para rascunho
        'rejected_rh->approved_manager' => 'admin',    // Reconsideração RH
    ];

    /**
     * Action types por transição para logs.
     */
    private const ACTION_TYPES = [
        'draft->pending_manager' => VacationLog::ACTION_SUBMITTED,
        'pending_manager->approved_manager' => VacationLog::ACTION_MANAGER_APPROVED,
        'approved_manager->approved_rh' => VacationLog::ACTION_HR_APPROVED,
        'approved_rh->in_progress' => VacationLog::ACTION_STARTED,
        'in_progress->completed' => VacationLog::ACTION_FINISHED,
        'pending_manager->rejected_manager' => VacationLog::ACTION_MANAGER_REJECTED,
        'approved_manager->rejected_rh' => VacationLog::ACTION_HR_REJECTED,
    ];

    /**
     * Valida se a transição é permitida.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateTransition(Vacation $vacation, string $newStatus, array $data = []): array
    {
        $errors = [];

        // Verificar se a transição é válida
        if (! $vacation->canTransitionTo($newStatus)) {
            $errors[] = "Transição de '{$vacation->status_label}' para '".(Vacation::STATUS_LABELS[$newStatus] ?? $newStatus)."' não é permitida.";

            return ['valid' => false, 'errors' => $errors];
        }

        // Rejeições e cancelamentos requerem motivo
        if (in_array($newStatus, [Vacation::STATUS_REJECTED_MANAGER, Vacation::STATUS_REJECTED_RH]) && empty($data['notes'])) {
            $errors[] = 'Motivo da rejeição é obrigatório.';
        }

        if ($newStatus === Vacation::STATUS_CANCELLED && empty($data['cancellation_reason'])) {
            $errors[] = 'Motivo do cancelamento é obrigatório.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Executa a transição com todos os side effects.
     */
    public function executeTransition(Vacation $vacation, string $newStatus, array $data, int $userId): void
    {
        DB::transaction(function () use ($vacation, $newStatus, $data, $userId) {
            $oldStatus = $vacation->status;
            $transitionKey = "{$oldStatus}->{$newStatus}";

            // Atualizar campos específicos da transição
            $updateData = ['status' => $newStatus, 'updated_by_user_id' => $userId];

            switch ($newStatus) {
                case Vacation::STATUS_APPROVED_MANAGER:
                    $updateData['manager_approved_by_user_id'] = $userId;
                    $updateData['manager_approved_at'] = now();
                    $updateData['manager_notes'] = $data['notes'] ?? null;
                    break;

                case Vacation::STATUS_APPROVED_RH:
                    $updateData['hr_approved_by_user_id'] = $userId;
                    $updateData['hr_approved_at'] = now();
                    $updateData['hr_notes'] = $data['notes'] ?? null;
                    break;

                case Vacation::STATUS_IN_PROGRESS:
                    $this->startVacation($vacation);
                    break;

                case Vacation::STATUS_COMPLETED:
                    $updateData['finalized_at'] = now();
                    $this->finishVacation($vacation);
                    break;

                case Vacation::STATUS_CANCELLED:
                    $updateData['cancelled_by_user_id'] = $userId;
                    $updateData['cancelled_at'] = now();
                    $updateData['cancellation_reason'] = $data['cancellation_reason'] ?? $data['notes'] ?? null;
                    if ($oldStatus === Vacation::STATUS_IN_PROGRESS) {
                        $this->revertVacation($vacation);
                    }
                    break;

                case Vacation::STATUS_REJECTED_MANAGER:
                    $updateData['rejected_by_user_id'] = $userId;
                    $updateData['rejected_at'] = now();
                    $updateData['rejection_reason'] = $data['notes'] ?? null;
                    $updateData['manager_notes'] = $data['notes'] ?? null;
                    break;

                case Vacation::STATUS_REJECTED_RH:
                    $updateData['rejected_by_user_id'] = $userId;
                    $updateData['rejected_at'] = now();
                    $updateData['rejection_reason'] = $data['notes'] ?? null;
                    $updateData['hr_notes'] = $data['notes'] ?? null;
                    break;

                case Vacation::STATUS_DRAFT:
                    // Voltando para rascunho (após rejeição)
                    $updateData['rejected_by_user_id'] = null;
                    $updateData['rejected_at'] = null;
                    $updateData['rejection_reason'] = null;
                    break;
            }

            $vacation->update($updateData);

            // Log da transição
            $this->logAction(
                $vacation->id,
                self::ACTION_TYPES[$transitionKey] ?? VacationLog::ACTION_CANCELLED,
                $oldStatus,
                $newStatus,
                $userId,
                $data['notes'] ?? null
            );
        });
    }

    /**
     * Registra log de ação.
     */
    public function logAction(int $vacationId, string $actionType, ?string $oldStatus, string $newStatus, int $userId, ?string $notes = null): void
    {
        VacationLog::create([
            'vacation_id' => $vacationId,
            'action_type' => $actionType,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by_user_id' => $userId,
            'notes' => $notes,
        ]);
    }

    /**
     * Side effect: Iniciar férias (status → in_progress).
     * - Atualiza days_taken no período
     * - Muda status do funcionário para "Férias" (4)
     */
    private function startVacation(Vacation $vacation): void
    {
        // Atualizar período
        $period = $vacation->vacationPeriod;
        $period->increment('days_taken', $vacation->days_quantity);

        if ($vacation->sell_days > 0) {
            $period->increment('sell_days', $vacation->sell_days);
        }

        // Atualizar status do período
        if ($period->days_taken >= $period->days_entitled) {
            $period->update(['status' => VacationPeriod::STATUS_SETTLED]);
        } else {
            $period->update(['status' => VacationPeriod::STATUS_PARTIALLY_TAKEN]);
        }

        // Salvar status anterior e mudar para Férias
        $employee = $vacation->employee;
        $vacation->update(['previous_employee_status' => $employee->status_id]);
        $employee->update(['status_id' => 4]); // 4 = Férias
    }

    /**
     * Side effect: Finalizar férias (status → completed).
     * - Restaura status anterior do funcionário
     */
    private function finishVacation(Vacation $vacation): void
    {
        $employee = $vacation->employee;
        $previousStatus = $vacation->previous_employee_status ?? 2; // 2 = Ativo
        $employee->update(['status_id' => $previousStatus]);
    }

    /**
     * Side effect: Reverter férias (cancelamento de férias em gozo).
     * - Reverte days_taken no período
     * - Restaura status do funcionário
     */
    private function revertVacation(Vacation $vacation): void
    {
        // Reverter período
        $period = $vacation->vacationPeriod;
        $period->decrement('days_taken', $vacation->days_quantity);

        if ($vacation->sell_days > 0) {
            $period->decrement('sell_days', $vacation->sell_days);
        }

        // Recalcular status do período
        if ($period->days_taken <= 0) {
            $period->update(['status' => VacationPeriod::STATUS_AVAILABLE]);
        } else {
            $period->update(['status' => VacationPeriod::STATUS_PARTIALLY_TAKEN]);
        }

        // Restaurar status do funcionário
        $employee = $vacation->employee;
        $previousStatus = $vacation->previous_employee_status ?? 2;
        $employee->update(['status_id' => $previousStatus]);
    }
}
