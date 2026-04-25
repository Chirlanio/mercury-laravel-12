<?php

namespace App\Listeners;

use App\Enums\AccountabilityStatus;
use App\Enums\Permission;
use App\Enums\TravelExpenseStatus;
use App\Events\TravelExpenseStatusChanged;
use App\Models\User;
use App\Notifications\TravelExpenseStatusChangedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Escuta TravelExpenseStatusChanged e notifica stakeholders por database+mail.
 *
 * Matriz de destinatários (excluindo sempre o actor da ação):
 *
 *  Solicitação (kind='expense'):
 *   - → submitted:   APPROVE_TRAVEL_EXPENSES (Financeiro/Contas a Pagar)
 *   - → approved:    criador + beneficiado
 *   - → rejected:    criador + beneficiado
 *   - → cancelled:   criador + beneficiado (se actor != criador)
 *   - → finalized:   criador + beneficiado
 *   - → draft (volta): criador
 *
 *  Prestação (kind='accountability'):
 *   - → submitted:   APPROVE_TRAVEL_EXPENSES
 *   - → approved:    criador + beneficiado
 *   - → rejected:    criador + beneficiado (volta para correção)
 *   - outros (pending/in_progress): silêncio (transição interna)
 *
 * Falhas NÃO quebram o fluxo de transição (estamos pós-commit).
 *
 * Auto-discovery do Laravel 12 registra esta classe automaticamente —
 * NÃO chamar Event::listen() manualmente em providers (causaria
 * duplicação de notificações).
 */
class NotifyTravelExpenseStakeholders
{
    public function handle(TravelExpenseStatusChanged $event): void
    {
        try {
            $recipients = $this->resolveRecipients($event);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new TravelExpenseStatusChangedNotification(
                    travelExpense: $event->travelExpense,
                    fromStatus: $event->fromStatus,
                    toStatus: $event->toStatus,
                    actor: $event->actor,
                    note: $event->note,
                    kind: $event->kind,
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify travel-expense stakeholders', [
                'travel_expense_id' => $event->travelExpense->id,
                'kind' => $event->kind,
                'from' => $event->fromStatus->value,
                'to' => $event->toStatus->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function resolveRecipients(TravelExpenseStatusChanged $event)
    {
        $te = $event->travelExpense;
        $actorId = $event->actor?->id;

        // SUBMITTED em qualquer kind notifica os aprovadores (Financeiro)
        $isSubmittedTransition = $event->toStatus === TravelExpenseStatus::SUBMITTED
            || $event->toStatus === AccountabilityStatus::SUBMITTED;

        if ($isSubmittedTransition) {
            return User::query()
                ->whereNotNull('email')
                ->when($actorId, fn ($q) => $q->where('id', '!=', $actorId))
                ->get()
                ->filter(fn (User $u) => $u->hasPermissionTo(Permission::APPROVE_TRAVEL_EXPENSES->value))
                ->values();
        }

        // Demais transições notificam criador + beneficiado (employee.user_id)
        $userIds = collect();

        if ($te->created_by_user_id && $te->created_by_user_id !== $actorId) {
            $userIds->push($te->created_by_user_id);
        }

        // Tentativa de resolver "user do beneficiado" via employee
        $employeeUserId = $this->resolveEmployeeUserId($te);
        if ($employeeUserId
            && $employeeUserId !== $actorId
            && ! $userIds->contains($employeeUserId)) {
            $userIds->push($employeeUserId);
        }

        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $userIds->all())
            ->whereNotNull('email')
            ->get();
    }

    /**
     * Beneficiado pode ou não ter login no sistema. Se houver vínculo
     * Employee→User, devolve o user_id; caso contrário null.
     *
     * Não falha se o relacionamento não existir (módulo Employees pode
     * variar entre versões — algumas têm user_id, outras não).
     */
    protected function resolveEmployeeUserId(\App\Models\TravelExpense $te): ?int
    {
        $employee = $te->employee;
        if (! $employee) {
            return null;
        }

        // Tentativa direta via coluna user_id (se existir no schema)
        if (isset($employee->attributes['user_id'])) {
            return $employee->user_id ? (int) $employee->user_id : null;
        }

        return null;
    }
}
