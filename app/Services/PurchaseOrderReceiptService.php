<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra recebimentos manuais e automáticos de ordens de compra.
 *
 * Responsável por:
 *  - Criar receipt + receipt_items em transaction
 *  - Atualizar quantity_received agregado em purchase_order_items
 *  - Disparar transição automática da ordem:
 *      - todos itens fully received → DELIVERED (via PurchaseOrderTransitionService)
 *      - parcial e ordem ainda pendente → PARTIAL_INVOICED
 *
 * O matcher CIGAM (PurchaseOrderCigamMatcherService) chama register() com
 * source='cigam_match' e null em created_by_user_id.
 */
class PurchaseOrderReceiptService
{
    public function __construct(
        protected PurchaseOrderTransitionService $transitionService,
    ) {}

    /**
     * @param  array  $items  Array de ['purchase_order_item_id' => int, 'quantity' => int, ...optional cigam fields]
     *
     * @throws ValidationException
     */
    public function register(
        PurchaseOrder $order,
        array $items,
        ?User $actor = null,
        ?string $invoiceNumber = null,
        ?string $notes = null,
        string $source = PurchaseOrderReceipt::SOURCE_MANUAL,
        ?string $batchId = null,
    ): PurchaseOrderReceipt {
        if ($order->is_deleted) {
            throw ValidationException::withMessages([
                'order' => 'Não é possível registrar recebimento em ordem excluída.',
            ]);
        }

        if ($order->status === PurchaseOrderStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'order' => 'Não é possível registrar recebimento em ordem cancelada.',
            ]);
        }

        if ($order->status === PurchaseOrderStatus::DELIVERED) {
            throw ValidationException::withMessages([
                'order' => 'Esta ordem já está totalmente entregue.',
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Informe ao menos um item recebido.',
            ]);
        }

        $source = $source === PurchaseOrderReceipt::SOURCE_CIGAM_MATCH
            ? PurchaseOrderReceipt::SOURCE_CIGAM_MATCH
            : PurchaseOrderReceipt::SOURCE_MANUAL;

        return DB::transaction(function () use ($order, $items, $actor, $invoiceNumber, $notes, $source, $batchId) {
            $receipt = PurchaseOrderReceipt::create([
                'purchase_order_id' => $order->id,
                'received_at' => now(),
                'invoice_number' => $invoiceNumber,
                'notes' => $notes,
                'source' => $source,
                'matched_sync_batch_id' => $batchId,
                'created_by_user_id' => $actor?->id,
            ]);

            foreach ($items as $row) {
                $itemId = $row['purchase_order_item_id'] ?? null;
                $qty = (int) ($row['quantity'] ?? 0);
                if (! $itemId || $qty <= 0) {
                    continue;
                }

                $item = PurchaseOrderItem::where('purchase_order_id', $order->id)
                    ->where('id', $itemId)
                    ->lockForUpdate()
                    ->first();

                if (! $item) {
                    throw ValidationException::withMessages([
                        'items' => "Item id={$itemId} não pertence à ordem #{$order->order_number}.",
                    ]);
                }

                $remaining = $item->quantity_ordered - $item->quantity_received;
                if ($qty > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => "Quantidade ({$qty}) excede o saldo do item {$item->reference}/{$item->size} (restante: {$remaining}).",
                    ]);
                }

                PurchaseOrderReceiptItem::create([
                    'receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $item->id,
                    'quantity_received' => $qty,
                    'matched_movement_id' => $row['matched_movement_id'] ?? null,
                    'unit_cost_cigam' => $row['unit_cost_cigam'] ?? null,
                    'created_at' => now(),
                ]);

                $item->increment('quantity_received', $qty);
            }

            $this->autoTransitionAfterReceipt($order->fresh('items'), $actor);

            return $receipt->fresh(['items.purchaseOrderItem', 'createdBy']);
        });
    }

    /**
     * Após registrar um recebimento, decide se a ordem deve transicionar
     * automaticamente:
     *  - Todos os itens atingiram quantity_ordered → DELIVERED
     *  - Algum item recebeu (mas nem tudo) e ordem está PENDING/INVOICED → PARTIAL_INVOICED
     *
     * O actor pode ser null (caminho do matcher CIGAM). Nesse caso usamos
     * um "system user" implícito: passamos o created_by_user_id da ordem.
     * Isto é necessário porque PurchaseOrderTransitionService::authorizeTransition
     * verifica permissions do actor — e o matcher roda em background sem usuário.
     *
     * Para receipts automáticos (matcher), não disparamos transição se o
     * actor for null e a transição exigir permissions específicas. Em vez
     * disso, deixamos a ordem como está e a transição manual fica como
     * caminho de confirmação.
     */
    protected function autoTransitionAfterReceipt(PurchaseOrder $order, ?User $actor): void
    {
        $totalOrdered = (int) $order->items->sum('quantity_ordered');
        $totalReceived = (int) $order->items->sum('quantity_received');

        if ($totalOrdered === 0) {
            return;
        }

        // Sem actor (matcher CIGAM rodando em background): não força
        // transição. O usuário pode confirmar manualmente depois.
        if (! $actor) {
            return;
        }

        if ($totalReceived >= $totalOrdered) {
            // 100% recebido — vai para DELIVERED (se transição válida)
            if ($order->canTransitionTo(PurchaseOrderStatus::DELIVERED)) {
                try {
                    $this->transitionService->transition(
                        $order,
                        PurchaseOrderStatus::DELIVERED,
                        $actor,
                        'Recebimento total registrado'
                    );
                } catch (ValidationException $e) {
                    // Se a transição falhar (ex: usuário sem RECEIVE_PURCHASE_ORDERS),
                    // o receipt fica gravado mas o status não muda. Não bloqueia.
                }
            }

            return;
        }

        // Recebimento parcial: se a ordem ainda está PENDING, move para
        // PARTIAL_INVOICED (significa: NF parcial recebida).
        if ($order->status === PurchaseOrderStatus::PENDING
            && $order->canTransitionTo(PurchaseOrderStatus::PARTIAL_INVOICED)) {
            try {
                $this->transitionService->transition(
                    $order,
                    PurchaseOrderStatus::PARTIAL_INVOICED,
                    $actor,
                    'Recebimento parcial registrado'
                );
            } catch (ValidationException $e) {
                // mesma razão acima
            }
        }
    }
}
