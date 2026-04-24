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
        protected ConsignmentLookupService $lookup,
    ) {
    }

    /**
     * Registra um evento de retorno. Transação all-or-nothing.
     *
     * Cada item suporta 2 ações:
     *  - 'returned' : produto voltou fisicamente — incrementa returned_quantity
     *  - 'sold'     : produto foi vendido ao cliente — incrementa sold_quantity
     *                 e valida no CIGAM se houve movement_code=2 pro CPF
     *                 do cliente nos 7 dias após return_date. Se não
     *                 achou, exige `sale_justification` e dispara email
     *                 pra loja (ConsignmentSaleUnconfirmedNotification).
     *
     * NF de retorno (store_code + invoice_number + movement_date) é
     * OBRIGATÓRIA e compõe a chave. Se houver itens 'sold' sem venda
     * confirmada no CIGAM, `sale_justification` vira obrigatória.
     *
     * @param  array{
     *   return_invoice_number: string,
     *   return_date: string,
     *   return_store_code: string,
     *   movement_id?: ?int,
     *   notes?: ?string,
     *   sale_justification?: ?string,
     * }  $data
     * @param  array<int, array{
     *   consignment_item_id: int,
     *   quantity: int,
     *   action?: 'returned'|'sold',
     * }>  $items
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
                'items' => 'Informe ao menos um item devolvido ou vendido.',
            ]);
        }

        // NF de retorno é OBRIGATÓRIA (chave composta com loja + data)
        $requiredKeys = ['return_invoice_number', 'return_date', 'return_store_code'];
        foreach ($requiredKeys as $k) {
            if (empty($data[$k])) {
                throw ValidationException::withMessages([
                    $k => 'Campo obrigatório — NF de retorno requer número, data e loja.',
                ]);
            }
        }

        // Consolida por (item_id, action) — permite mesmo item com partes returned+sold
        $consolidated = []; // [itemId => ['returned' => N, 'sold' => M]]
        $itemJustifications = []; // [itemId => justificativa por-item]
        foreach ($items as $row) {
            $id = (int) ($row['consignment_item_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);
            $action = $row['action'] ?? 'returned';

            if ($id <= 0 || $qty <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Item inválido: consignment_item_id e quantity > 0 são obrigatórios.',
                ]);
            }
            if (! in_array($action, ['returned', 'sold'], true)) {
                throw ValidationException::withMessages([
                    'items' => "Ação inválida '{$action}'. Aceito: returned, sold.",
                ]);
            }

            if (! isset($consolidated[$id])) {
                $consolidated[$id] = ['returned' => 0, 'sold' => 0];
            }
            $consolidated[$id][$action] += $qty;

            if ($action === 'sold' && ! empty($row['sale_justification'])) {
                $itemJustifications[$id] = trim((string) $row['sale_justification']);
            }
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

        // Valida soma (returned + sold) ≤ pendente por item
        foreach ($consolidated as $itemId => $qtyMap) {
            $item = $consignmentItems[$itemId];
            $pending = (int) $item->pending_quantity;
            $total = $qtyMap['returned'] + $qtyMap['sold'];

            if ($total > $pending) {
                throw ValidationException::withMessages([
                    'items' => "Item {$item->reference}: soma (devolvido {$qtyMap['returned']} + vendido {$qtyMap['sold']}) = {$total} excede o pendente ({$pending}).",
                ]);
            }
        }

        // Validação de venda no CIGAM: se algum item foi marcado como
        // 'sold', verifica movement_code=2 pelo CPF do cliente na janela
        // de 7 dias. O match é POR PRODUTO CONSIGNADO (barcode/ref_size),
        // não só pelo CPF — venda genérica do cliente não confirma venda
        // do item consignado. Sem confirmação, justificativa por item
        // (ou fallback global) é obrigatória.
        $soldItemsIds = array_keys(array_filter(
            $consolidated,
            fn ($q) => $q['sold'] > 0,
        ));
        $saleConfirmedInCigam = false;
        $unconfirmedSaleItems = [];
        $globalJustification = isset($data['sale_justification']) ? trim((string) $data['sale_justification']) : '';

        if (! empty($soldItemsIds)) {
            $consignment->setRelation('items', $consignmentItems->values());
            $verify = $this->lookup->verifyCustomerSale(
                $consignment->recipient_document_clean,
                $data['return_date'],
                7,
                $consignment,
            );
            $perItemMatches = $verify['per_item'];

            $saleConfirmedInCigam = true; // só true se TODOS os soldItems tiverem match

            foreach ($soldItemsIds as $id) {
                $matched = (bool) ($perItemMatches[$id]['matched'] ?? false);
                if ($matched) {
                    continue;
                }

                $saleConfirmedInCigam = false;
                $itemJustification = $itemJustifications[$id] ?? $globalJustification;

                if ($itemJustification === '') {
                    $item = $consignmentItems[$id];
                    throw ValidationException::withMessages([
                        'items' => sprintf(
                            'Venda do produto %s (Tam. %s) sem confirmação no CIGAM — justificativa é obrigatória.',
                            $item->reference,
                            $item->size_label ?: $item->size_cigam_code ?: '—',
                        ),
                    ]);
                }

                $item = $consignmentItems[$id];
                $unconfirmedSaleItems[] = [
                    'consignment_item_id' => $item->id,
                    'reference' => $item->reference,
                    'size_label' => $item->size_label ?: $item->size_cigam_code,
                    'quantity' => $consolidated[$id]['sold'],
                    'justification' => $itemJustification,
                ];
            }
        }

        return DB::transaction(function () use (
            $consignment, $data, $consolidated, $consignmentItems, $actor,
            $saleConfirmedInCigam, $unconfirmedSaleItems, $globalJustification,
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

            $totalReturnedQty = 0;
            $totalReturnedValue = 0.0;
            $totalSoldQty = 0;

            foreach ($consolidated as $itemId => $qtyMap) {
                /** @var ConsignmentItem $item */
                $item = $consignmentItems[$itemId];
                $unitValue = (float) $item->unit_value;

                // Parcela 'returned' — grava no pivô consignment_return_items
                if ($qtyMap['returned'] > 0) {
                    $qty = $qtyMap['returned'];
                    $subtotal = round($qty * $unitValue, 2);

                    ConsignmentReturnItem::create([
                        'consignment_return_id' => $return->id,
                        'consignment_item_id' => $item->id,
                        'quantity' => $qty,
                        'unit_value' => $unitValue,
                        'subtotal' => $subtotal,
                    ]);

                    $item->returned_quantity = (int) $item->returned_quantity + $qty;
                    $totalReturnedQty += $qty;
                    $totalReturnedValue += $subtotal;
                }

                // Parcela 'sold' — incrementa sold_quantity no item.
                // Não vai no pivô do retorno (sold não é retorno físico).
                if ($qtyMap['sold'] > 0) {
                    $item->sold_quantity = (int) $item->sold_quantity + $qtyMap['sold'];
                    $totalSoldQty += $qtyMap['sold'];
                }

                $item->refreshDerivedStatus()->save();
            }

            $totalQty = $totalReturnedQty; // pro update abaixo (mantém compat com UI)
            $totalValue = $totalReturnedValue;

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
                        'sold_quantity' => $totalSoldQty,
                        'sale_confirmed_cigam' => $saleConfirmedInCigam,
                    ],
                );
            }

            // Venda não confirmada → grava justificativa (consolidada por
            // item) no histórico e dispara notificação (email + database)
            // pra gerência/loja. Cada item mantém sua própria justificativa.
            if (! empty($unconfirmedSaleItems)) {
                $combinedJustification = collect($unconfirmedSaleItems)
                    ->map(fn ($it) => sprintf(
                        '[%s%s] %s',
                        $it['reference'],
                        $it['size_label'] ? ' Tam.'.$it['size_label'] : '',
                        $it['justification'] ?? '—',
                    ))
                    ->implode(' | ');

                \App\Models\ConsignmentStatusHistory::create([
                    'consignment_id' => $fresh->id,
                    'from_status' => $fresh->status->value,
                    'to_status' => $fresh->status->value,
                    'changed_by_user_id' => $actor->id,
                    'note' => 'Venda alegada sem confirmação CIGAM — '.$combinedJustification,
                    'context' => [
                        'sale_unconfirmed' => true,
                        'items' => $unconfirmedSaleItems,
                        'justification' => $globalJustification ?: null,
                    ],
                    'created_at' => now(),
                ]);

                $this->dispatchSaleUnconfirmedNotification(
                    $fresh,
                    $unconfirmedSaleItems,
                    $combinedJustification,
                    $actor,
                );
            }

            return $return->fresh(['items', 'consignment']);
        });
    }

    /**
     * Envia alerta de venda não confirmada para a loja + supervisão.
     *
     * Destinatários:
     *  - Usuários com MANAGE_CONSIGNMENTS (supervisão ampla)
     *  - Usuários da mesma loja da consignação (store_id)
     *
     * Silencioso em caso de falha — ação de UI deve continuar mesmo
     * se o email/sino não disparar.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function dispatchSaleUnconfirmedNotification(
        Consignment $consignment,
        array $items,
        string $justification,
        User $actor,
    ): void {
        try {
            $store = $consignment->store;
            $storeCode = $consignment->outbound_store_code;

            $recipientIds = User::query()
                ->where(function ($q) use ($storeCode) {
                    $q->whereHas('employee', fn ($e) => $e->where('store_id', $storeCode))
                        ->orWhereNotNull('id'); // fallback — filtramos abaixo
                })
                ->pluck('id')
                ->all();

            // Recupera como Collection pra filtrar por permissão em runtime
            $candidates = User::query()->whereIn('id', $recipientIds)->get();

            $recipients = $candidates->filter(
                fn (User $u) => $u->hasPermissionTo(\App\Enums\Permission::MANAGE_CONSIGNMENTS->value)
                    || $u->id === $consignment->created_by_user_id
                    || (isset($u->store_id) && $u->store_id === $storeCode),
            );

            if ($recipients->isEmpty()) {
                \Illuminate\Support\Facades\Log::warning('Consignment sale unconfirmed — no recipients', [
                    'consignment_id' => $consignment->id,
                ]);

                return;
            }

            \Illuminate\Support\Facades\Notification::send(
                $recipients,
                new \App\Notifications\ConsignmentSaleUnconfirmedNotification(
                    $consignment,
                    $items,
                    $justification,
                    $actor->name,
                ),
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to dispatch sale-unconfirmed notification', [
                'consignment_id' => $consignment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
