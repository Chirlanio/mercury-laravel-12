<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentStatusHistory;
use App\Models\User;
use App\Notifications\StockAdjustment\StockAdjustmentStatusChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class StockAdjustmentTransitionService
{
    /**
     * Valida se a transição é permitida levando em conta o usuário.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public function validateTransition(StockAdjustment $adjustment, string $newStatus, User $user): array
    {
        $errors = [];

        if ($adjustment->deleted_at !== null) {
            $errors[] = 'Ajuste excluído não pode receber transições.';

            return ['valid' => false, 'errors' => $errors];
        }

        if (! $adjustment->canTransitionTo($newStatus)) {
            $from = StockAdjustment::STATUS_LABELS[$adjustment->status] ?? $adjustment->status;
            $to = StockAdjustment::STATUS_LABELS[$newStatus] ?? $newStatus;
            $errors[] = "Transição de '{$from}' para '{$to}' não permitida.";
        }

        // Reabertura de cancelado: somente admin
        if ($newStatus === 'pending' && $adjustment->status === 'cancelled' && ! $this->isAdmin($user)) {
            $errors[] = 'Apenas administradores podem reabrir ajustes cancelados.';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Executa a transição de status registrando histórico.
     */
    public function executeTransition(StockAdjustment $adjustment, string $newStatus, User $user, ?string $notes = null): StockAdjustment
    {
        $validation = $this->validateTransition($adjustment, $newStatus, $user);
        if (! $validation['valid']) {
            throw new InvalidArgumentException(implode(' ', $validation['errors']));
        }

        $oldStatus = $adjustment->status;

        $fresh = DB::transaction(function () use ($adjustment, $newStatus, $user, $notes, $oldStatus) {
            StockAdjustmentStatusHistory::create([
                'stock_adjustment_id' => $adjustment->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by_user_id' => $user->id,
                'notes' => $notes,
            ]);

            $adjustment->update(['status' => $newStatus]);

            return $adjustment->fresh(['statusHistory', 'createdBy', 'store']);
        });

        // Notifica o criador (best-effort)
        try {
            if ($fresh->createdBy) {
                Notification::send(
                    $fresh->createdBy,
                    new StockAdjustmentStatusChangedNotification($fresh, $oldStatus, $newStatus, $notes),
                );
            }
        } catch (\Throwable $e) {
            // Ignora falhas de notificação
        }

        return $fresh;
    }

    /**
     * Executa transições em lote (máx 50 por segurança).
     *
     * @param  array<int>  $ids
     * @return array{success: int, failed: int, errors: array<string>}
     */
    public function bulkTransition(array $ids, string $newStatus, User $user, ?string $notes = null): array
    {
        $ids = array_slice(array_unique($ids), 0, 50);
        $result = ['success' => 0, 'failed' => 0, 'errors' => []];

        $adjustments = StockAdjustment::whereIn('id', $ids)->get();

        foreach ($adjustments as $adjustment) {
            try {
                $this->executeTransition($adjustment, $newStatus, $user, $notes);
                $result['success']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = "#{$adjustment->id}: ".$e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Lista as transições permitidas a partir do status atual, já filtradas por papel.
     *
     * @return array<string, string>  status => label
     */
    public function allowedTransitions(StockAdjustment $adjustment, User $user): array
    {
        $allowed = StockAdjustment::VALID_TRANSITIONS[$adjustment->status] ?? [];
        $result = [];

        foreach ($allowed as $status) {
            // Reabrir só para admin
            if ($status === 'pending' && $adjustment->status === 'cancelled' && ! $this->isAdmin($user)) {
                continue;
            }

            $result[$status] = StockAdjustment::STATUS_LABELS[$status] ?? $status;
        }

        return $result;
    }

    private function isAdmin(User $user): bool
    {
        $role = $user->role?->value ?? null;

        return in_array($role, [Role::SUPER_ADMIN->value, Role::ADMIN->value], true);
    }
}
