<?php

namespace App\Services;

use App\Models\OrderPayment;
use App\Models\User;

class OrderPaymentDeleteService
{
    /**
     * Check if a user can delete an order payment.
     * 3-level permission system matching legacy v1.
     *
     * Level 1: Creator can delete own backlog orders that were never edited
     * Level 2: Admin can delete backlog/doing orders (requires reason)
     * Level 3: Super Admin can delete any order (requires reason + confirmation)
     */
    public function canDelete(OrderPayment $order, User $user): array
    {
        // Already deleted
        if ($order->is_deleted) {
            return [
                'allowed' => false,
                'require_reason' => false,
                'require_confirmation' => false,
                'message' => 'Esta ordem de pagamento já foi excluída.',
                'level' => 0,
            ];
        }

        $role = $user->role->value;

        // Level 1: Creator deleting own backlog order that was never edited
        if (
            $order->status === OrderPayment::STATUS_BACKLOG
            && $order->created_by_user_id === $user->id
            && $order->updated_by_user_id === null
        ) {
            return [
                'allowed' => true,
                'require_reason' => false,
                'require_confirmation' => false,
                'message' => 'Você pode excluir esta solicitação.',
                'level' => 1,
            ];
        }

        // Level 2: Admin can delete backlog/doing orders
        if (
            in_array($order->status, [OrderPayment::STATUS_BACKLOG, OrderPayment::STATUS_DOING])
            && in_array($role, ['super_admin', 'admin'])
        ) {
            return [
                'allowed' => true,
                'require_reason' => true,
                'require_confirmation' => false,
                'message' => 'Exclusão requer motivo.',
                'level' => 2,
            ];
        }

        // Level 3: Super Admin can delete waiting/done orders
        if (
            in_array($order->status, [OrderPayment::STATUS_WAITING, OrderPayment::STATUS_DONE])
            && $role === 'super_admin'
        ) {
            return [
                'allowed' => true,
                'require_reason' => true,
                'require_confirmation' => true,
                'message' => 'Exclusão de ordem paga/lançada requer motivo e confirmação.',
                'level' => 3,
            ];
        }

        return [
            'allowed' => false,
            'require_reason' => false,
            'require_confirmation' => false,
            'message' => 'Sem permissão para excluir esta ordem de pagamento.',
            'level' => 0,
        ];
    }

    /**
     * Execute soft delete
     */
    public function softDelete(OrderPayment $order, User $user, ?string $reason = null): bool
    {
        $order->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => $user->id,
            'delete_reason' => $reason,
        ]);

        // Record in status history
        app(OrderPaymentTransitionService::class)->recordStatusHistory(
            $order->id,
            $order->status,
            'deleted',
            $user->id,
            $reason ? "Excluído: {$reason}" : 'Excluído'
        );

        return true;
    }

    /**
     * Restore a soft-deleted order (Super Admin only)
     */
    public function restore(OrderPayment $order, User $user): bool
    {
        if ($user->role->value !== 'super_admin') {
            return false;
        }

        $order->update([
            'deleted_at' => null,
            'deleted_by_user_id' => null,
            'delete_reason' => null,
        ]);

        app(OrderPaymentTransitionService::class)->recordStatusHistory(
            $order->id,
            'deleted',
            $order->status,
            $user->id,
            'Restaurado por administrador'
        );

        return true;
    }
}
