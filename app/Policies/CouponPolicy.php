<?php

namespace App\Policies;

use App\Enums\CouponStatus;
use App\Enums\Permission;
use App\Models\Coupon;
use App\Models\User;

/**
 * Policy para Coupon — respeita store scoping automático quando o
 * usuário NÃO tem MANAGE_COUPONS (segue padrão Reversals/Returns).
 *
 * Métodos:
 *  - viewAny: tem VIEW_COUPONS?
 *  - view(coupon): VIEW_COUPONS + (MANAGE_COUPONS OU store_code combina)
 *  - create: CREATE_COUPONS (escopo é validado pelo service ao checar store_code)
 *  - update(coupon): EDIT_COUPONS + view scope + estado editável
 *  - delete(coupon): DELETE_COUPONS + view scope + não-emitido
 *  - issueCode(coupon): ISSUE_COUPON_CODE (equipe e-commerce — sem scope)
 *  - cancel(coupon): EDIT_COUPONS OU MANAGE_COUPONS OU DELETE_COUPONS
 *  - export: EXPORT_COUPONS
 */
class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::VIEW_COUPONS->value);
    }

    public function view(User $user, Coupon $coupon): bool
    {
        if (! $user->hasPermissionTo(Permission::VIEW_COUPONS->value)) {
            return false;
        }

        if ($user->hasPermissionTo(Permission::MANAGE_COUPONS->value)) {
            return true;
        }

        // Store scoping: vê apenas cupons da própria loja.
        // Para Influencer (sem store_code) só quem tem MANAGE_COUPONS ou o criador vê.
        if ($coupon->store_code === null) {
            return $coupon->created_by_user_id === $user->id;
        }

        return $coupon->store_code === $user->store_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::CREATE_COUPONS->value);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        if (! $user->hasPermissionTo(Permission::EDIT_COUPONS->value)
            && ! $user->hasPermissionTo(Permission::MANAGE_COUPONS->value)) {
            return false;
        }

        if (! $this->view($user, $coupon)) {
            return false;
        }

        // Em estados avançados (issued/active/expired/cancelled), só MANAGE edita
        $earlyStates = [CouponStatus::DRAFT, CouponStatus::REQUESTED];
        if (! in_array($coupon->status, $earlyStates, true)
            && ! $user->hasPermissionTo(Permission::MANAGE_COUPONS->value)) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        if (! $user->hasPermissionTo(Permission::DELETE_COUPONS->value)) {
            return false;
        }

        if (! $this->view($user, $coupon)) {
            return false;
        }

        // Service faz a validação fina (não permite excluir emitido)
        return true;
    }

    public function issueCode(User $user, Coupon $coupon): bool
    {
        // E-commerce (ISSUE_COUPON_CODE) pode emitir qualquer cupom —
        // bypassa store scoping por natureza da operação.
        return $user->hasPermissionTo(Permission::ISSUE_COUPON_CODE->value);
    }

    public function cancel(User $user, Coupon $coupon): bool
    {
        $canCancel = $user->hasPermissionTo(Permission::EDIT_COUPONS->value)
            || $user->hasPermissionTo(Permission::MANAGE_COUPONS->value)
            || $user->hasPermissionTo(Permission::DELETE_COUPONS->value);

        return $canCancel && $this->view($user, $coupon);
    }

    public function export(User $user): bool
    {
        return $user->hasPermissionTo(Permission::EXPORT_COUPONS->value);
    }
}
