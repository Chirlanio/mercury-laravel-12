<?php

namespace App\Listeners;

use App\Enums\Permission;
use App\Enums\ReversalStatus;
use App\Events\ReversalStatusChanged;
use App\Models\User;
use App\Notifications\ReversalStatusChangedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Escuta ReversalStatusChanged e notifica stakeholders via database
 * notification (sino do frontend).
 *
 * Matriz de destinatários por transição (excluindo sempre o actor):
 *  - → pending_authorization: quem pode aprovar (APPROVE_REVERSALS).
 *  - → authorized: criador + quem processa (PROCESS_REVERSALS).
 *  - → pending_finance: quem processa (PROCESS_REVERSALS).
 *  - → reversed: criador do estorno.
 *  - → cancelled: criador do estorno.
 *  - outros estados intermediários: MANAGE_REVERSALS (visão global).
 *
 * Falhas no envio NÃO devem quebrar o fluxo de transição (já estamos
 * pós-commit do banco).
 */
class NotifyReversalStakeholders
{
    public function handle(ReversalStatusChanged $event): void
    {
        try {
            $recipients = $this->resolveRecipients($event);

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new ReversalStatusChangedNotification(
                reversal: $event->reversal,
                fromStatus: $event->fromStatus,
                toStatus: $event->toStatus,
                actor: $event->actor,
                note: $event->note,
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify reversal stakeholders', [
                'reversal_id' => $event->reversal->id,
                'from' => $event->fromStatus->value,
                'to' => $event->toStatus->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function resolveRecipients(ReversalStatusChanged $event)
    {
        $actorId = $event->actor->id;
        $storeCode = $event->reversal->store_code;
        $creatorId = $event->reversal->created_by_user_id;
        $to = $event->toStatus;

        $candidates = User::query()->where('id', '!=', $actorId)->get();

        return $candidates->filter(function (User $user) use ($to, $storeCode, $creatorId) {
            // Visao global sempre recebe (exceto o actor, ja filtrado acima)
            if ($user->hasPermissionTo(Permission::MANAGE_REVERSALS->value)
                && $user->store_id === $storeCode) {
                // dono da loja com visao global — ver abaixo (evita duplicar)
            }

            return match ($to) {
                ReversalStatus::PENDING_AUTHORIZATION => $this->canApproveInStore($user, $storeCode),

                ReversalStatus::AUTHORIZED,
                ReversalStatus::PENDING_FINANCE => $user->id === $creatorId
                    || $this->canProcessInStore($user, $storeCode),

                ReversalStatus::REVERSED,
                ReversalStatus::CANCELLED => $user->id === $creatorId,

                default => $user->hasPermissionTo(Permission::MANAGE_REVERSALS->value),
            };
        })->values();
    }

    protected function canApproveInStore(User $user, string $storeCode): bool
    {
        if (! $user->hasPermissionTo(Permission::APPROVE_REVERSALS->value)) {
            return false;
        }

        return $user->hasPermissionTo(Permission::MANAGE_REVERSALS->value)
            || $user->store_id === $storeCode;
    }

    protected function canProcessInStore(User $user, string $storeCode): bool
    {
        if (! $user->hasPermissionTo(Permission::PROCESS_REVERSALS->value)) {
            return false;
        }

        return $user->hasPermissionTo(Permission::MANAGE_REVERSALS->value)
            || $user->store_id === $storeCode;
    }
}
