<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de ordens de compra. Não manipula status além da criação (estado
 * inicial = pending) — transições devem passar pelo
 * PurchaseOrderTransitionService.
 */
class PurchaseOrderService
{
    /**
     * Cria uma nova ordem de compra em estado pending. Grava a linha
     * inicial no histórico de status (from=null).
     *
     * @throws ValidationException
     */
    public function create(array $data, User $actor): PurchaseOrder
    {
        $this->validateBusinessRules($data);

        return DB::transaction(function () use ($data, $actor) {
            $order = PurchaseOrder::create([
                'order_number' => $data['order_number'],
                'short_description' => $data['short_description'] ?? null,
                'season' => $data['season'],
                'collection' => $data['collection'],
                'release_name' => $data['release_name'],
                'supplier_id' => $data['supplier_id'],
                'store_id' => $data['store_id'],
                'brand_id' => $data['brand_id'] ?? null,
                'order_date' => $data['order_date'],
                'predict_date' => $data['predict_date'] ?? null,
                'payment_terms_raw' => $data['payment_terms_raw'] ?? null,
                'auto_generate_payments' => $data['auto_generate_payments'] ?? false,
                'status' => PurchaseOrderStatus::PENDING->value,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            PurchaseOrderStatusHistory::create([
                'purchase_order_id' => $order->id,
                'from_status' => null,
                'to_status' => PurchaseOrderStatus::PENDING->value,
                'changed_by_user_id' => $actor->id,
                'note' => 'Ordem criada',
                'created_at' => now(),
            ]);

            return $order->fresh(['supplier', 'store', 'brand', 'items', 'statusHistory']);
        });
    }

    /**
     * Atualiza dados de cabeçalho da ordem. Status só pode ser alterado
     * pelo PurchaseOrderTransitionService — se vier no payload, é ignorado.
     *
     * @throws ValidationException
     */
    public function update(PurchaseOrder $order, array $data, User $actor): PurchaseOrder
    {
        if ($order->is_deleted) {
            throw ValidationException::withMessages([
                'order' => 'Não é possível editar uma ordem excluída.',
            ]);
        }

        // Ordem só pode ser editada quando pendente
        if ($order->status !== PurchaseOrderStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Ordens em estado diferente de Pendente não podem ter dados de cabeçalho alterados.',
            ]);
        }

        // Remove campos que não podem ser mudados por update
        unset($data['status'], $data['created_by_user_id'], $data['deleted_at'], $data['deleted_by_user_id']);

        $this->validateBusinessRules($data, excludeId: $order->id);

        $order->fill($data);
        $order->updated_by_user_id = $actor->id;
        $order->save();

        return $order->fresh(['supplier', 'store', 'brand', 'items', 'statusHistory']);
    }

    /**
     * Soft delete — marca deleted_at + deleted_by_user_id + deleted_reason.
     * Bloqueia se houver dependências financeiras vinculadas.
     *
     * @throws ValidationException
     */
    public function delete(PurchaseOrder $order, User $actor, string $reason): void
    {
        if ($order->is_deleted) {
            throw ValidationException::withMessages([
                'order' => 'Ordem já foi excluída.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'deleted_reason' => 'É obrigatório informar o motivo da exclusão.',
            ]);
        }

        // Bloquear exclusão se há OrderPayment vinculada (Fase 3 adiciona a FK).
        // Por ora, verificamos via DB::hasTable para ser forward-compatible.
        if (\Schema::hasColumn('order_payments', 'purchase_order_id')) {
            $hasPayments = DB::table('order_payments')
                ->where('purchase_order_id', $order->id)
                ->exists();

            if ($hasPayments) {
                throw ValidationException::withMessages([
                    'order' => 'Não é possível excluir: existem ordens de pagamento vinculadas.',
                ]);
            }
        }

        $order->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => $actor->id,
            'deleted_reason' => $reason,
            'updated_by_user_id' => $actor->id,
        ]);
    }

    /**
     * Regras de negócio centralizadas (usadas por create e update).
     *
     * @throws ValidationException
     */
    protected function validateBusinessRules(array $data, ?int $excludeId = null): void
    {
        // Datas
        if (! empty($data['order_date']) && ! empty($data['predict_date'])) {
            if (strtotime($data['predict_date']) < strtotime($data['order_date'])) {
                throw ValidationException::withMessages([
                    'predict_date' => 'A data de previsão não pode ser anterior à data do pedido.',
                ]);
            }
        }

        // Unicidade do order_number
        if (! empty($data['order_number'])) {
            $query = PurchaseOrder::where('order_number', trim($data['order_number']));
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'order_number' => 'Já existe uma ordem de compra com este número.',
                ]);
            }
        }
    }
}
