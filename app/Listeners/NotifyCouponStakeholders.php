<?php

namespace App\Listeners;

use App\Enums\CouponStatus;
use App\Enums\Permission;
use App\Events\CouponStatusChanged;
use App\Models\User;
use App\Notifications\CouponStatusChangedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Escuta CouponStatusChanged e notifica stakeholders via database
 * notification (sino do frontend).
 *
 * Matriz de destinatários por transição (excluindo sempre o actor):
 *  - → requested: usuários com ISSUE_COUPON_CODE (equipe e-commerce)
 *    precisam saber que há pedido para emitir.
 *  - → issued: criador recebe (saber que seu código saiu + coupon_site).
 *  - → active: criador recebe (saber que está publicado).
 *  - → cancelled: criador recebe (saber motivo).
 *  - → expired: criador recebe (informativo).
 *  - outros: silêncio (draft é interno, actor já sabe).
 *
 * Falhas NÃO quebram o fluxo de transição (já estamos pós-commit).
 */
class NotifyCouponStakeholders
{
    public function handle(CouponStatusChanged $event): void
    {
        try {
            $recipients = $this->resolveRecipients($event);

            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new CouponStatusChangedNotification(
                coupon: $event->coupon,
                fromStatus: $event->fromStatus,
                toStatus: $event->toStatus,
                actor: $event->actor,
                note: $event->note,
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to notify coupon stakeholders', [
                'coupon_id' => $event->coupon->id,
                'from' => $event->fromStatus->value,
                'to' => $event->toStatus->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function resolveRecipients(CouponStatusChanged $event)
    {
        $actorId = $event->actor?->id;
        $creatorId = $event->coupon->created_by_user_id;
        $to = $event->toStatus;

        $candidates = User::query();
        if ($actorId) {
            $candidates->where('id', '!=', $actorId);
        }
        $candidates = $candidates->get();

        return $candidates->filter(function (User $user) use ($to, $creatorId) {
            return match ($to) {
                CouponStatus::REQUESTED => $user->hasPermissionTo(Permission::ISSUE_COUPON_CODE->value),

                CouponStatus::ISSUED,
                CouponStatus::ACTIVE,
                CouponStatus::CANCELLED,
                CouponStatus::EXPIRED => $user->id === $creatorId,

                default => false,
            };
        })->values();
    }
}
