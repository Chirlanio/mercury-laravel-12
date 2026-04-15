<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\Movement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Casa movimentos do CIGAM com itens de ordens de compra abertas.
 *
 * Regra de matching:
 *  - movements.movement_code = 17 (Ordem de Compra) ← cod_movimento do CIGAM
 *  - movements.entry_exit = 'E' (entrada)
 *  - movements.store_code = purchase_order.store_id
 *  - movements.movement_date >= purchase_order.order_date
 *  - movements.ref_size casa com purchase_order_items.reference + size
 *    (CIGAM concatena referência + tamanho no campo `reftam`)
 *  - movements.id ainda não está vinculado a nenhum receipt_item
 *    (idempotência via UNIQUE index em matched_movement_id)
 *
 * Cria 1 receipt agrupado por (invoice_number, movement_date), source='cigam_match'.
 *
 * Idempotente: pode ser chamado N vezes que só vai criar receipts pra
 * movements ainda não vinculados.
 *
 * Não dispara auto-transição (ver PurchaseOrderReceiptService::autoTransitionAfterReceipt
 * — pula quando actor é null). O usuário confirma manualmente depois,
 * porque o matcher pode estar errado e queremos um humano no loop.
 */
class PurchaseOrderCigamMatcherService
{
    public const CIGAM_PURCHASE_ENTRY_CODE = 17;

    public function __construct(
        protected PurchaseOrderReceiptService $receiptService,
    ) {}

    /**
     * Procura matches para uma ordem específica.
     *
     * @return array{receipts_created: int, items_matched: int, movements_scanned: int}
     */
    public function matchOrder(PurchaseOrder $order): array
    {
        if ($order->is_deleted || $order->status === PurchaseOrderStatus::CANCELLED) {
            return ['receipts_created' => 0, 'items_matched' => 0, 'movements_scanned' => 0];
        }

        $items = $order->items()->get();
        if ($items->isEmpty()) {
            return ['receipts_created' => 0, 'items_matched' => 0, 'movements_scanned' => 0];
        }

        // Mapa rápido: ref_size (reference + size concatenados) → item
        // O CIGAM grava ref_size de várias formas; tentamos:
        //   - "{reference}{size}" (sem separador)
        //   - "{reference} {size}" (com espaço)
        //   - "{reference}-{size}" (com traço)
        // Cobrimos as 3 e quem casar primeiro vence.
        $itemsByRefSize = [];
        foreach ($items as $item) {
            foreach ($this->candidateRefSizes($item->reference, $item->size) as $key) {
                $itemsByRefSize[$key] = $item;
            }
        }

        // Movements candidatos: code 17 + entrada + loja + data >= order_date
        // + não vinculados ainda (subquery)
        $movements = Movement::query()
            ->where('movement_code', self::CIGAM_PURCHASE_ENTRY_CODE)
            ->where('entry_exit', 'E')
            ->where('store_code', $order->store_id)
            ->whereDate('movement_date', '>=', $order->order_date->toDateString())
            ->whereNotIn('id', function ($sub) {
                $sub->select('matched_movement_id')
                    ->from('purchase_order_receipt_items')
                    ->whereNotNull('matched_movement_id');
            })
            ->get();

        if ($movements->isEmpty()) {
            return [
                'receipts_created' => 0,
                'items_matched' => 0,
                'movements_scanned' => 0,
            ];
        }

        // Agrupa por (invoice_number, movement_date) — uma NF pode ter N items
        $groups = $movements->groupBy(function (Movement $m) {
            $invoice = $m->invoice_number ?: 'NO_INVOICE';
            $date = $m->movement_date->toDateString();
            return "{$invoice}|{$date}";
        });

        $receiptsCreated = 0;
        $itemsMatched = 0;

        foreach ($groups as $group) {
            $receiptItems = [];
            $batchId = $group->first()->sync_batch_id;
            $invoice = $group->first()->invoice_number;
            $receivedAt = $group->first()->movement_date;

            foreach ($group as $movement) {
                $key = $this->normalizeRefSize($movement->ref_size);
                $item = $itemsByRefSize[$key] ?? null;
                if (! $item) {
                    continue;
                }

                $remaining = $item->quantity_ordered - $item->quantity_received;
                if ($remaining <= 0) {
                    continue;
                }

                $qty = (int) min($remaining, $movement->quantity);
                if ($qty <= 0) {
                    continue;
                }

                $receiptItems[] = [
                    'purchase_order_item_id' => $item->id,
                    'quantity' => $qty,
                    'matched_movement_id' => $movement->id,
                    'unit_cost_cigam' => (float) $movement->cost_price,
                ];

                // Reflete localmente pra próxima iteração não exceder o saldo
                $item->quantity_received += $qty;
                $itemsMatched++;
            }

            if (empty($receiptItems)) {
                continue;
            }

            try {
                $this->receiptService->register(
                    order: $order->fresh(),
                    items: $receiptItems,
                    actor: null,
                    invoiceNumber: $invoice,
                    notes: 'Recebimento detectado automaticamente do CIGAM',
                    source: PurchaseOrderReceipt::SOURCE_CIGAM_MATCH,
                    batchId: $batchId,
                );

                $receiptsCreated++;
            } catch (\Throwable $e) {
                Log::warning('CIGAM match failed for purchase order', [
                    'order_id' => $order->id,
                    'invoice' => $invoice,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'receipts_created' => $receiptsCreated,
            'items_matched' => $itemsMatched,
            'movements_scanned' => $movements->count(),
        ];
    }

    /**
     * Varre todas as ordens ativas e tenta matchear. Usado pelo command
     * `purchase-orders:cigam-match` (Fase 4).
     *
     * @return array{orders_processed: int, receipts_created: int, items_matched: int}
     */
    public function matchAllActive(): array
    {
        $orders = PurchaseOrder::query()
            ->notDeleted()
            ->whereIn('status', [
                PurchaseOrderStatus::PENDING->value,
                PurchaseOrderStatus::INVOICED->value,
                PurchaseOrderStatus::PARTIAL_INVOICED->value,
            ])
            ->get();

        $totalReceipts = 0;
        $totalItems = 0;

        foreach ($orders as $order) {
            $result = $this->matchOrder($order);
            $totalReceipts += $result['receipts_created'];
            $totalItems += $result['items_matched'];
        }

        return [
            'orders_processed' => $orders->count(),
            'receipts_created' => $totalReceipts,
            'items_matched' => $totalItems,
        ];
    }

    /**
     * Gera as variações possíveis de "ref_size" que o CIGAM pode ter
     * gravado. Normalizadas para upper + sem espaços extras.
     *
     * @return array<int, string>
     */
    protected function candidateRefSizes(string $reference, string $size): array
    {
        $ref = strtoupper(trim($reference));
        $sz = strtoupper(trim($size));

        return array_unique([
            $this->normalizeRefSize($ref . $sz),
            $this->normalizeRefSize("{$ref} {$sz}"),
            $this->normalizeRefSize("{$ref}-{$sz}"),
        ]);
    }

    protected function normalizeRefSize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/\s+/', '', strtoupper(trim($value)));
    }
}
