<?php

namespace App\Listeners;

use App\Enums\Permission;
use App\Enums\ReturnStatus;
use App\Events\ReturnOrderStatusChanged;
use App\Models\User;
use App\Notifications\ReturnOrderStatusChangedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Escuta ReturnOrderStatusChanged e notifica stakeholders via database
 * notification (sino do frontend).
 *
 * Matriz de destinatários por transição (excluindo sempre o actor):
 *  - → approved: criador recebe (saber que foi aprovada);
 *    aprovadores recebem (visão geral).
 *  - → awaiting_product / processing: criador + quem processa.
 *  - → completed: criador (recebe confirmação).
 *  - → cancelled: criador (recebe motivo).
 *  - → pending (regressão): aprovadores.
 *  - outros estados: MANAGE_RETURNS (visão global).
 *
 * Falhas NÃO quebram o fluxo de transição (já estamos pós-commit).
 */
class NotifyReturnOrderStakeholders
{
    public function handle(ReturnOrderStatusChanged $event): void
    {
        try {
            $recipients = $this->resolveRecipients($event);

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new ReturnOrderStatusChangedNotification(
                returnOrder: $event->returnOrder,
                fromStatus: $event->fromStatus,
                toStatus: $event->toStatus,
                actor: $event->actor,
                note: $event->note,
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify return order stakeholders', [
                'return_order_id' => $event->returnOrder->id,
                'from' => $event->fromStatus->value,
                'to' => $event->toStatus->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function resolveRecipients(ReturnOrderStatusChanged $event)
    {
        $actorId = $event->actor->id;
        $storeCode = $event->returnOrder->store_code;
        $creatorId = $event->returnOrder->created_by_user_id;
        $to = $event->toStatus;

        $candidates = User::query()->where('id', '!=', $actorId)->get();

        return $candidates->filter(function (User $user) use ($to, $storeCode, $creatorId) {
            return match ($to) {
                ReturnStatus::APPROVED => $user->id === $creatorId
                    || $this->canApproveInStore($user, $storeCode),

                ReturnStatus::AWAITING_PRODUCT,
                ReturnStatus::PROCESSING => $user->id === $creatorId
                    || $this->canProcessInStore($user, $storeCode),

                ReturnStatus::COMPLETED,
                ReturnStatus::CANCELLED => $user->id === $creatorId,

                ReturnStatus::PENDING => $this->canApproveInStore($user, $storeCode),

                default => $user->hasPermissionTo(Permission::MANAGE_RETURNS->value),
            };
        })->values();
    }

    protected function canApproveInStore(User $user, string $storeCode): bool
    {
        if (! $user->hasPermissionTo(Permission::APPROVE_RETURNS->value)) {
            return false;
        }

        return $user->hasPermissionTo(Permission::MANAGE_RETURNS->value)
            || $user->store_id === $storeCode;
    }

    protected function canProcessInStore(User $user, string $storeCode): bool
    {
        if (! $user->hasPermissionTo(Permission::PROCESS_RETURNS->value)) {
            return false;
        }

        return $user->hasPermissionTo(Permission::MANAGE_RETURNS->value)
            || $user->store_id === $storeCode;
    }
}
