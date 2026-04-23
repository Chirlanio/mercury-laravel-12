<?php

namespace App\Services;

use App\Enums\ConsignmentItemStatus;
use App\Enums\ConsignmentStatus;
use App\Enums\Permission;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\ConsignmentReturn;
use App\Models\ConsignmentReturnItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registro de retorno de consignação — materializa a regra M1
 * ("itens da NF de retorno devem ser os mesmos da NF de saída, com
 * quantidade ≤ pendente por item").
 *
 * Suporta retorno parcial em múltiplas NFs: cliente devolve parte hoje,
 * resto em outra visita. Cada evento gera um ConsignmentReturn novo
 * (imutável) com seus próprios itens via pivô consignment_return_items.
 *
 * Após registrar:
 *  1. Incrementa `returned_quantity` em cada consignment_item
 *  2. Recalcula status derivado do item via refreshDerivedStatus()
 *  3. Chama ConsignmentService::refreshTotals() na consignação
 *  4. Se todos os items estão resolvidos (pending_quantity=0) → transita
 *     para `completed`. Senão, se já houve retorno → `partially_returned`.
 */
class ConsignmentReturnService
{
    public function __construct(
        protected ConsignmentTransitionService $transitions,
        protected ConsignmentService $consignments,
    ) {
    }

    /**
     * Registra um evento de retorno. Transação garante all-or-nothing.
     *
     * Estrutura de cada linha em `items`:
     *  - consignment_item_id: int (obrigatório — FK)
     *  - quantity: int ≥ 1
     *
     * Validações (regra M1):
     *  - cada item pertence à consignação (consignment_id casa)
     *  - quantity ≤ pending_quantity do item
     *  - soma agregada de cada item nunca excede a quantidade original
     *
     * @param  array{
     *   return_invoice_number?: ?string,
     *   return_date: string,
     *   return_store_code?: ?string,
     *   movement_id?: ?int,
     *   notes?: ?string,
     * }  $data
     * @param  array<int, array{consignment_item_id: int, quantity: int}>  $items
     *
     * @throws ValidationException
     */
    public function register(
        Consignment $consignment,
        array $data,
        array $items,
        User $actor,
    ): ConsignmentReturn {
        if (! $actor->hasPermissionTo(Permission::REGISTER_CONSIGNMENT_RETURN->value)) {
            throw ValidationException::withMessages([
                'permission' => 'Você não tem permissão para lançar retornos de consignação.',
            ]);
        }

        if ($consignment->is_deleted) {
            throw ValidationException::withMessages([
                'consignment' => 'Consignação excluída — lançamento de retorno bloqueado.',
            ]);
        }

        if ($consignment->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => "Consignação em estado terminal ({$consignment->status->label()}) não aceita novo retorno.",
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Informe ao menos um item devolvido.',
            ]);
        }

        // Consolida quantidade por item (ignora duplicados no input)
        $consolidated = [];
        foreach ($items as $row) {
            $id = (int) ($row['consignment_item_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);

            if ($id <= 0 || $qty <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Item inválido: consignment_item_id e quantity > 0 são obrigatórios.',
                ]);
            }

            $consolidated[$id] = ($consolidated[$id] ?? 0) + $qty;
        }

        // Busca os items atuais (regra M1 — confronto com itens de saída)
        /** @var \Illuminate\Support\Collection<int, ConsignmentItem> $consignmentItems */
        $consignmentItems = $consignment->items()
            ->whereIn('id', array_keys($consolidated))
            ->get()
            ->keyBy('id');

        if ($consignmentItems->count() !== count($consolidated)) {
            $missing = array_diff(array_keys($consolidated), $consignmentItems->keys()->all());
            throw ValidationException::withMessages([
                'items' => 'Item(ns) não pertence(m) a esta consignação: '.implode(', ', $missing).'. Regra M1: nota de retorno só aceita itens da nota de saída.',
            ]);
        }

        // Valida quantidade ≤ pendente por item
        foreach ($consolidated as $itemId => $qty) {
            $item = $consignmentItems[$itemId];
            $pending = $item->pending_quantity;

            if ($qty > $pending) {
                throw ValidationException::withMessages([
                    'items' => "Item {$item->reference}: quantidade informada ({$qty}) excede o pendente ({$pending}).",
                ]);
            }
        }

        return DB::transaction(function () use (
            $consignment, $data, $consolidated, $consignmentItems, $actor
        ) {
            // Cria o evento de retorno
            $return = ConsignmentReturn::create([
                'consignment_id' => $consignment->id,
                'return_invoice_number' => $data['return_invoice_number'] ?? null,
                'return_date' => $data['return_date'],
                'return_store_code' => $data['return_store_code'] ?? $consignment->outbound_store_code,
                'movement_id' => $data['movement_id'] ?? null,
                'reconciled_at' => ! empty($data['movement_id']) ? now() : null,
                'notes' => $data['notes'] ?? null,
                'registered_by_user_id' => $actor->id,
            ]);

            $totalQty = 0;
            $totalValue = 0.0;

            foreach ($consolidated as $itemId => $qty) {
                /** @var ConsignmentItem $item */
                $item = $consignmentItems[$itemId];

                $unitValue = (float) $item->unit_value;
                $subtotal = round($qty * $unitValue, 2);

                ConsignmentReturnItem::create([
                    'consignment_return_id' => $return->id,
                    'consignment_item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_value' => $unitValue,
                    'subtotal' => $subtotal,
                ]);

                $item->returned_quantity = (int) $item->returned_quantity + $qty;
                $item->refreshDerivedStatus()->save();

                $totalQty += $qty;
                $totalValue += $subtotal;
            }

            $return->update([
                'returned_quantity' => $totalQty,
                'returned_value' => round($totalValue, 2),
            ]);

            // Recalcula totais agregados na consignação
            $fresh = $this->consignments->refreshTotals($consignment);

            // Determina transição alvo:
            // - se todos os items resolvidos → completed
            // - senão → partially_returned (ou mantém overdue se já estava)
            $hasOpenItems = $fresh->items()
                ->whereIn('status', [
                    ConsignmentItemStatus::PENDING->value,
                    ConsignmentItemStatus::PARTIAL->value,
                ])
                ->exists();

            $targetStatus = null;
            if (! $hasOpenItems) {
                // Todos os items fechados — pode finalizar
                if ($fresh->status !== ConsignmentStatus::COMPLETED) {
                    $targetStatus = ConsignmentStatus::COMPLETED;
                }
            } else {
                // Parcial — se estava pending/overdue, marca partially_returned
                if (in_array($fresh->status, [
                    ConsignmentStatus::PENDING,
                    ConsignmentStatus::OVERDUE,
                ], true)) {
                    $targetStatus = ConsignmentStatus::PARTIALLY_RETURNED;
                }
            }

            if ($targetStatus && $fresh->canTransitionTo($targetStatus)) {
                $this->transitions->transition(
                    $fresh,
                    $targetStatus,
                    $actor,
                    "Retorno registrado — NF {$return->return_invoice_number}",
                    [
                        'consignment_return_id' => $return->id,
                        'returned_quantity' => $totalQty,
                    ],
                );
            }

            return $return->fresh(['items', 'consignment']);
        });
    }
}
