<?php

namespace App\Events;

use App\Enums\CouponStatus;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado após uma transição de status de Coupon bem-sucedida,
 * pelo CouponTransitionService. A mutação já foi commitada no banco
 * quando este evento dispara.
 *
 * actor pode ser null apenas em transições automáticas (expiração
 * via command coupons:expire-stale).
 *
 * Consumidores:
 *  - NotifyCouponStakeholders: cria notificações database (sino) para
 *    criador/emissores conforme a transição.
 */
class CouponStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Coupon $coupon,
        public readonly CouponStatus $fromStatus,
        public readonly CouponStatus $toStatus,
        public readonly ?User $actor,
        public readonly ?string $note = null,
    ) {}
}
