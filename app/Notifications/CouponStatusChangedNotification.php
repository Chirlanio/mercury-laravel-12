<?php

namespace App\Notifications;

use App\Enums\CouponStatus;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notificação database (sino) quando um cupom muda de status.
 * Disparada pelo NotifyCouponStakeholders em resposta ao evento
 * CouponStatusChanged.
 *
 * Apenas database — sem mail — pra não inundar caixa postal com cada
 * transição. Mail fica reservado pro command coupons:remind-pending
 * diário (Fase 6).
 */
class CouponStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Coupon $coupon,
        public CouponStatus $fromStatus,
        public CouponStatus $toStatus,
        public ?User $actor,
        public ?string $note,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'coupon_status_changed',
            'coupon_id' => $this->coupon->id,
            'coupon_type' => $this->coupon->type?->value,
            'coupon_type_label' => $this->coupon->type?->label(),
            'beneficiary_name' => $this->coupon->beneficiary_name,
            'store_code' => $this->coupon->store_code,
            'coupon_site' => $this->coupon->coupon_site,
            'suggested_coupon' => $this->coupon->suggested_coupon,
            'from_status' => $this->fromStatus->value,
            'from_status_label' => $this->fromStatus->label(),
            'to_status' => $this->toStatus->value,
            'to_status_label' => $this->toStatus->label(),
            'actor_name' => $this->actor?->name,
            'note' => $this->note,
        ];
    }
}
