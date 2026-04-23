<?php

namespace App\Services;

use App\Enums\CouponStatus;
use App\Enums\Permission;
use App\Events\CouponStatusChanged;
use App\Models\Coupon;
use App\Models\CouponStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State machine de cupons. Ponto único de mutação de Coupon::status.
 * Outros serviços e controllers NUNCA devem setar o campo direto.
 *
 * Transições válidas (CouponStatus::allowedTransitions):
 *   draft → requested | cancelled
 *   requested → issued | cancelled
 *   issued → active | expired | cancelled
 *   active → expired | cancelled
 *   expired → [] (terminal)
 *   cancelled → [] (terminal)
 *
 * Permissões por transição:
 *  - draft → requested: exige EDIT_COUPONS ou CREATE_COUPONS (autor do rascunho)
 *  - requested → issued: exige ISSUE_COUPON_CODE + coupon_site preenchido no context
 *  - issued → active: exige ISSUE_COUPON_CODE
 *  - * → cancelled: exige EDIT_COUPONS ou MANAGE_COUPONS + motivo
 *  - * → expired: automático (command coupons:expire-stale), actor = null aceito
 *
 * Ao transitar para:
 *  - requested: grava requested_at
 *  - issued: grava issued_at + issued_by_user_id + coupon_site (obrigatório no context)
 *  - active: grava activated_at
 *  - expired: grava expired_at
 *  - cancelled: grava cancelled_at + cancelled_reason (obrigatório)
 */
class CouponTransitionService
{
    /**
     * @param  array{coupon_site?:string,...}  $context
     *
     * @throws ValidationException
     */
    public function transition(
        Coupon $coupon,
        CouponStatus|string $toStatus,
        ?User $actor,
        ?string $note = null,
        array $context = []
    ): Coupon {
        if ($coupon->is_deleted) {
            throw ValidationException::withMessages([
                'coupon' => 'Não é possível transicionar um cupom excluído.',
            ]);
        }

        $target = $toStatus instanceof CouponStatus ? $toStatus : CouponStatus::from($toStatus);
        $current = $coupon->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Transição inválida: {$current->label()} → {$target->label()}.",
            ]);
        }

        // Expiração automática pode rodar sem actor (command agendado)
        if ($target !== CouponStatus::EXPIRED) {
            if ($actor === null) {
                throw ValidationException::withMessages([
                    'actor' => 'Usuário responsável pela transição é obrigatório.',
                ]);
            }

            $this->authorizeTransition($current, $target, $actor);
        }

        // Cancelamento exige motivo
        if ($target === CouponStatus::CANCELLED && (! $note || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'É obrigatório informar o motivo do cancelamento.',
            ]);
        }

        // Emissão exige código no context
        if ($target === CouponStatus::ISSUED) {
            $code = $context['coupon_site'] ?? null;
            if (! $code || trim((string) $code) === '') {
                throw ValidationException::withMessages([
                    'coupon_site' => 'Código do cupom é obrigatório para emissão.',
                ]);
            }

            $this->ensureUniqueCouponCode($code, $coupon->id);
        }

        return DB::transaction(function () use ($coupon, $current, $target, $actor, $note, $context) {
            $update = [
                'status' => $target->value,
            ];

            if ($actor) {
                $update['updated_by_user_id'] = $actor->id;
            }

            if ($target === CouponStatus::REQUESTED) {
                $update['requested_at'] = now();
            }

            if ($target === CouponStatus::ISSUED) {
                $update['issued_at'] = now();
                $update['issued_by_user_id'] = $actor?->id;
                $update['coupon_site'] = $context['coupon_site'];
            }

            if ($target === CouponStatus::ACTIVE) {
                $update['activated_at'] = now();
            }

            if ($target === CouponStatus::EXPIRED) {
                $update['expired_at'] = now();
            }

            if ($target === CouponStatus::CANCELLED) {
                $update['cancelled_at'] = now();
                $update['cancelled_reason'] = $note;
            }

            $coupon->update($update);

            CouponStatusHistory::create([
                'coupon_id' => $coupon->id,
                'from_status' => $current->value,
                'to_status' => $target->value,
                'changed_by_user_id' => $actor?->id,
                'note' => $note,
                'created_at' => now(),
            ]);

            $fresh = $coupon->fresh(['employee', 'store', 'socialMedia', 'statusHistory']);

            CouponStatusChanged::dispatch($fresh, $current, $target, $actor, $note);

            return $fresh;
        });
    }

    /**
     * Convenience — transiciona draft→requested. Disparado quando o
     * usuário salva o cupom e clica "Solicitar" (ou na criação com
     * autoRequest=true).
     */
    public function request(Coupon $coupon, User $actor): Coupon
    {
        return $this->transition($coupon, CouponStatus::REQUESTED, $actor, 'Solicitação enviada ao e-commerce');
    }

    /**
     * Convenience — transiciona requested→issued com o código emitido.
     */
    public function issueCode(Coupon $coupon, string $couponSite, User $actor, ?string $note = null): Coupon
    {
        return $this->transition(
            $coupon,
            CouponStatus::ISSUED,
            $actor,
            $note ?? "Código emitido: {$couponSite}",
            ['coupon_site' => $couponSite]
        );
    }

    /**
     * Convenience — transiciona issued→active.
     */
    public function activate(Coupon $coupon, User $actor, ?string $note = null): Coupon
    {
        return $this->transition($coupon, CouponStatus::ACTIVE, $actor, $note);
    }

    /**
     * Convenience — cancelamento com motivo obrigatório.
     */
    public function cancel(Coupon $coupon, string $reason, User $actor): Coupon
    {
        return $this->transition($coupon, CouponStatus::CANCELLED, $actor, $reason);
    }

    /**
     * Convenience — expiração automática (sem actor).
     */
    public function expire(Coupon $coupon): Coupon
    {
        return $this->transition($coupon, CouponStatus::EXPIRED, null, 'Expirado automaticamente por valid_until vencido');
    }

    /**
     * @throws ValidationException
     */
    protected function authorizeTransition(
        CouponStatus $from,
        CouponStatus $to,
        User $actor
    ): void {
        // Cancelamento — aceita EDIT_COUPONS, MANAGE_COUPONS ou DELETE_COUPONS
        if ($to === CouponStatus::CANCELLED) {
            $canCancel = $actor->hasPermissionTo(Permission::EDIT_COUPONS->value)
                || $actor->hasPermissionTo(Permission::MANAGE_COUPONS->value)
                || $actor->hasPermissionTo(Permission::DELETE_COUPONS->value);

            if (! $canCancel) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para cancelar cupons.',
                ]);
            }

            return;
        }

        // Emissão de código (requested → issued) exige ISSUE_COUPON_CODE
        if ($to === CouponStatus::ISSUED) {
            if (! $actor->hasPermissionTo(Permission::ISSUE_COUPON_CODE->value)) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para emitir códigos de cupom.',
                ]);
            }

            return;
        }

        // Ativação (issued → active) exige ISSUE_COUPON_CODE ou MANAGE_COUPONS
        if ($to === CouponStatus::ACTIVE) {
            $canActivate = $actor->hasPermissionTo(Permission::ISSUE_COUPON_CODE->value)
                || $actor->hasPermissionTo(Permission::MANAGE_COUPONS->value);

            if (! $canActivate) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para ativar cupons.',
                ]);
            }

            return;
        }

        // Solicitação (draft → requested) exige CREATE_COUPONS, EDIT_COUPONS ou MANAGE_COUPONS
        if ($to === CouponStatus::REQUESTED) {
            $canRequest = $actor->hasPermissionTo(Permission::CREATE_COUPONS->value)
                || $actor->hasPermissionTo(Permission::EDIT_COUPONS->value)
                || $actor->hasPermissionTo(Permission::MANAGE_COUPONS->value);

            if (! $canRequest) {
                throw ValidationException::withMessages([
                    'status' => 'Você não tem permissão para solicitar cupons.',
                ]);
            }

            return;
        }
    }

    /**
     * Evita colisão de código entre cupons ativos diferentes.
     *
     * @throws ValidationException
     */
    protected function ensureUniqueCouponCode(string $code, int $currentCouponId): void
    {
        $exists = Coupon::query()
            ->where('coupon_site', $code)
            ->where('id', '!=', $currentCouponId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', [CouponStatus::CANCELLED->value])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'coupon_site' => "O código '{$code}' já está em uso em outro cupom ativo.",
            ]);
        }
    }
}
