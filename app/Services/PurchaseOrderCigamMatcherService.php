<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\Movement;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Casa movimentos do CIGAM com itens de ordens de compra abertas.
 *
 * Estratégias de matching (em ordem de prioridade):
 *  1. ref_size — gera variações de reference+size e compara com movements.ref_size
 *  2. barcode — via catálogo: PO item → Product → ProductVariant.barcode → Movement.barcode
 *
 * Filtros base:
 *  - movements.movement_code = 1 (Compra) ← controle do CIGAM
 *  - movements.entry_exit = 'E' (entrada)
 *  - movements.movement_date >= purchase_order.order_date
 *  - movements.id ainda não está vinculado a nenhum receipt_item
 *  - SEM filtro de loja — é comum o fornecedor entregar no CD (Z443)
 *
 * Cria 1 receipt agrupado por (invoice_number, movement_date), source='cigam_match'.
 *
 * Idempotente: pode ser chamado N vezes que só vai criar receipts pra
 * movements ainda não vinculados.
 */
class PurchaseOrderCigamMatcherService
{
    /**
     * Código de movimento do CIGAM para entrada de compra.
     *
     * Inicialmente era 17 ("Ordem de Compra") — que existe na tabela
     * movement_types mas NUNCA aparece em movements reais. O código
     * correto é 1 ("Compra"), que tem 200k+ registros de entrada.
     */
    public const CIGAM_PURCHASE_ENTRY_CODE = 1;

    public function __construct(
        protected PurchaseOrderReceiptService $receiptService,
    ) {}

    /**
     * Procura matches para uma ordem específica.
     *
     * @return array{receipts_created: int, items_matched: int, movements_scanned: int, debug: array}
     */
    public function matchOrder(PurchaseOrder $order): array
    {
        if ($order->is_deleted || $order->status === PurchaseOrderStatus::CANCELLED) {
            return ['receipts_created' => 0, 'items_matched' => 0, 'movements_scanned' => 0, 'debug' => ['skip' => 'deleted or cancelled']];
        }

        $items = $order->items()->get();
        if ($items->isEmpty()) {
            return ['receipts_created' => 0, 'items_matched' => 0, 'movements_scanned' => 0, 'debug' => ['skip' => 'no items']];
        }

        // ── Mapa ref_size → item ─────────────────────────────────────
        // Duas fontes de candidatas para movements.ref_size:
        //  1. reference+size da planilha (sem product_id)
        //  2. product_variants.barcode via catálogo (com product_id)
        //     O CIGAM grava em product_variants.barcode o mesmo valor
        //     que usa em movements.ref_size (ex: "HT66513023.0135")
        $itemsByRefSize = [];

        // Fonte 1: variações de reference+size (itens sem product_id ou fallback)
        $sqlCandidates = [];
        foreach ($items as $item) {
            foreach ($this->normalizedCandidateRefSizes($item->reference, $item->size) as $key) {
                $itemsByRefSize[$key] = $item;
            }
            foreach ($this->sqlCandidateRefSizes($item->reference, $item->size) as $c) {
                $sqlCandidates[$c] = true;
            }
        }

        // Fonte 2: catalog barcodes como candidatas de ref_size
        // product_variants.barcode contém o mesmo código que movements.ref_size
        $catalogBarcodeMap = $this->buildBarcodeMap($items);
        $catalogMatchCount = count($catalogBarcodeMap);
        foreach ($catalogBarcodeMap as $barcode => $item) {
            // Adiciona ao mapa PHP (raw + normalizado)
            $itemsByRefSize[$barcode] = $item;
            $itemsByRefSize[$this->normalizeRefSize($barcode)] = $item;
            // Adiciona às candidatas SQL
            $sqlCandidates[$barcode] = true;
            $sqlCandidates[$this->normalizeRefSize($barcode)] = true;
        }

        $sqlCandidates = array_keys($sqlCandidates);

        // NFs dos itens (pra diagnóstico)
        $itemInvoiceNumbers = $items->pluck('invoice_number')->filter()->unique()->values()->all();

        if (empty($sqlCandidates)) {
            return ['receipts_created' => 0, 'items_matched' => 0, 'movements_scanned' => 0, 'debug' => ['skip' => 'no candidates']];
        }

        // ── Query: movements.ref_size IN (candidatas ref_size + catalog barcodes)
        $movements = Movement::query()
            ->where('movement_code', self::CIGAM_PURCHASE_ENTRY_CODE)
            ->where('entry_exit', 'E')
            ->whereDate('movement_date', '>=', $order->order_date->toDateString())
            ->whereIn('ref_size', $sqlCandidates)
            ->whereNotIn('id', function ($sub) {
                $sub->select('matched_movement_id')
                    ->from('purchase_order_receipt_items')
                    ->whereNotNull('matched_movement_id');
            })
            ->get();

        if ($movements->isEmpty()) {
            $debug = $this->diagnoseNoMatches($order, $sqlCandidates, [], $itemInvoiceNumbers);
            $debug['catalog_barcodes_as_ref_size'] = $catalogMatchCount;

            Log::info('CIGAM matcher: nenhum match encontrado', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'items_count' => $items->count(),
                'sql_candidates_count' => count($sqlCandidates),
                'catalog_barcodes_added' => $catalogMatchCount,
                'item_invoices' => $itemInvoiceNumbers,
                'debug' => $debug,
            ]);

            return [
                'receipts_created' => 0,
                'items_matched' => 0,
                'movements_scanned' => 0,
                'debug' => $debug,
            ];
        }

        Log::info('CIGAM matcher: movements encontrados', [
            'order_id' => $order->id,
            'movements_found' => $movements->count(),
            'sample_ref_sizes' => $movements->take(5)->pluck('ref_size')->all(),
            'catalog_barcodes_added' => $catalogMatchCount,
        ]);

        // ── Agrupa por (invoice_number, movement_date) ───────────────
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

            foreach ($group as $movement) {
                // Tenta match: normalizado, depois raw ref_size
                $item = $itemsByRefSize[$this->normalizeRefSize($movement->ref_size)] ?? null;
                if (! $item) {
                    $item = $itemsByRefSize[$movement->ref_size] ?? null;
                }

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
            'debug' => [],
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

    // ------------------------------------------------------------------
    // Barcode map (via catálogo de produtos)
    // ------------------------------------------------------------------

    /**
     * Monta mapa barcode → PurchaseOrderItem usando o catálogo.
     *
     * Cadeia: PO item.product_id → ProductVariant (por size) → barcode
     *
     * O import da planilha preenche product_id quando encontra um Product
     * com o mesmo reference. O ProductSyncService sincroniza variants com
     * barcode (cod_barras) e size_cigam_code do CIGAM.
     *
     * @return array<string, PurchaseOrderItem>  barcode → item
     */
    protected function buildBarcodeMap(Collection $items): array
    {
        $productIds = $items->pluck('product_id')->filter()->unique()->values()->all();
        if (empty($productIds)) {
            return [];
        }

        // Carrega todas as variants dos products envolvidos
        $variants = ProductVariant::whereIn('product_id', $productIds)
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->get();

        if ($variants->isEmpty()) {
            return [];
        }

        $variantsByProduct = $variants->groupBy('product_id');

        // Mapeia product_size_id → cigam_code pra casar com variant.size_cigam_code
        $productSizeIds = $items->pluck('product_size_id')->filter()->unique()->values()->all();
        $sizeCigamMap = [];
        if (! empty($productSizeIds)) {
            $sizeCigamMap = ProductSize::whereIn('id', $productSizeIds)
                ->pluck('cigam_code', 'id')
                ->all();
        }

        $map = [];

        foreach ($items as $item) {
            if (! $item->product_id) {
                continue;
            }

            $productVariants = $variantsByProduct[$item->product_id] ?? collect();
            if ($productVariants->isEmpty()) {
                continue;
            }

            $barcode = null;

            // Match 1: product_size_id → cigam_code → variant.size_cigam_code
            $cigamCode = $sizeCigamMap[$item->product_size_id] ?? null;
            if ($cigamCode) {
                $match = $productVariants->firstWhere('size_cigam_code', $cigamCode);
                if ($match && $match->barcode) {
                    $barcode = $match->barcode;
                }
            }

            // Match 2: produto tamanho único (1 variant)
            if (! $barcode && $productVariants->count() === 1) {
                $only = $productVariants->first();
                if ($only->barcode) {
                    $barcode = $only->barcode;
                }
            }

            // Match 3: size numérico → variant cujo size_cigam_code contém o size
            if (! $barcode && is_numeric($item->size)) {
                $match = $productVariants->first(function ($v) use ($item) {
                    return $v->barcode && str_contains($v->size_cigam_code ?? '', $item->size);
                });
                if ($match) {
                    $barcode = $match->barcode;
                }
            }

            if ($barcode) {
                $map[$barcode] = $item;
            }
        }

        return $map;
    }

    // ------------------------------------------------------------------
    // Candidatas ref_size
    // ------------------------------------------------------------------

    /**
     * Equivalências de tamanho entre planilha e CIGAM.
     * A planilha usa "01" pra tamanho unitário, o CIGAM usa "UN".
     */
    private const SIZE_EQUIVALENCES = [
        '01' => 'UN',
        'UN' => '01',
    ];

    /**
     * Candidatas NORMALIZADAS — usadas como chaves no mapa PHP
     * ($itemsByRefSize) para matching em memória.
     *
     * @return array<int, string>
     */
    protected function normalizedCandidateRefSizes(string $reference, string $size): array
    {
        $ref = strtoupper(trim($reference));
        $sz = strtoupper(trim($size));

        $candidates = [
            $this->normalizeRefSize($ref . $sz),
            $this->normalizeRefSize("{$ref} {$sz}"),
            $this->normalizeRefSize("{$ref}-{$sz}"),
            $this->normalizeRefSize("{$ref}U{$sz}"),
        ];

        $altSize = self::SIZE_EQUIVALENCES[$sz] ?? null;
        if ($altSize !== null) {
            $candidates[] = $this->normalizeRefSize($ref . $altSize);
            $candidates[] = $this->normalizeRefSize("{$ref} {$altSize}");
            $candidates[] = $this->normalizeRefSize("{$ref}U{$altSize}");
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Candidatas para SQL — inclui TANTO formas RAW (com espaço, traço)
     * quanto NORMALIZADAS (sem espaço). Necessário porque o DB armazena
     * o reftam do CIGAM apenas com trim(), sem normalizar.
     *
     * @return array<int, string>
     */
    protected function sqlCandidateRefSizes(string $reference, string $size): array
    {
        $ref = strtoupper(trim($reference));
        $sz = strtoupper(trim($size));

        $raw = [
            $ref . $sz,
            "{$ref} {$sz}",
            "{$ref}-{$sz}",
            "{$ref}U{$sz}",
        ];

        $normalized = array_map(fn ($v) => $this->normalizeRefSize($v), $raw);

        $all = array_merge($raw, $normalized);

        $altSize = self::SIZE_EQUIVALENCES[$sz] ?? null;
        if ($altSize !== null) {
            $rawAlt = [
                $ref . $altSize,
                "{$ref} {$altSize}",
                "{$ref}U{$altSize}",
            ];
            $normalizedAlt = array_map(fn ($v) => $this->normalizeRefSize($v), $rawAlt);
            $all = array_merge($all, $rawAlt, $normalizedAlt);
        }

        return array_values(array_unique(array_filter($all)));
    }

    /**
     * Alias para compatibilidade com testes existentes.
     */
    protected function candidateRefSizes(string $reference, string $size): array
    {
        return $this->sqlCandidateRefSizes($reference, $size);
    }

    protected function normalizeRefSize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/\s+/', '', strtoupper(trim($value)));
    }

    // ------------------------------------------------------------------
    // Diagnóstico
    // ------------------------------------------------------------------

    /**
     * Diagnóstico quando nenhum movement é encontrado.
     * Executa queries mais amplas pra identificar QUAL filtro está
     * eliminando os movimentos.
     */
    protected function diagnoseNoMatches(
        PurchaseOrder $order,
        array $sqlCandidates,
        array $unused,
        array $itemInvoiceNumbers
    ): array {
        $debug = [];

        // 1. Movimentos por NF
        if (! empty($itemInvoiceNumbers)) {
            $byInvoice = Movement::query()
                ->whereIn('invoice_number', $itemInvoiceNumbers)
                ->selectRaw('movement_code, entry_exit, COUNT(*) as cnt')
                ->groupBy('movement_code', 'entry_exit')
                ->get()
                ->map(fn ($r) => [
                    'movement_code' => $r->movement_code,
                    'entry_exit' => $r->entry_exit,
                    'count' => $r->cnt,
                ])
                ->all();

            $debug['movements_by_invoice'] = $byInvoice;

            // Amostras de code=1/E
            $samples = Movement::query()
                ->whereIn('invoice_number', $itemInvoiceNumbers)
                ->where('movement_code', self::CIGAM_PURCHASE_ENTRY_CODE)
                ->where('entry_exit', 'E')
                ->limit(5)
                ->get(['ref_size', 'barcode']);

            $debug['sample_ref_sizes_in_db'] = $samples->pluck('ref_size')->all();
            $debug['sample_barcodes_in_db'] = $samples->pluck('barcode')->all();
        }

        // 2. PO items com product_id
        $debug['items_with_product_id'] = $order->items()
            ->whereNotNull('product_id')
            ->count();

        // 3. Comparação: catalog barcodes vs movement ref_sizes
        if (! empty($itemInvoiceNumbers)) {
            $items = $order->items()->get();
            $catalogMap = $this->buildBarcodeMap($items);
            $catalogBarcodes = array_keys($catalogMap);

            $debug['catalog_barcodes_count'] = count($catalogBarcodes);
            $debug['catalog_barcodes_sample'] = array_slice($catalogBarcodes, 0, 3);

            // Quantos catalog barcodes existem como ref_size nos movements
            if (! empty($catalogBarcodes)) {
                $matchCount = Movement::query()
                    ->whereIn('invoice_number', $itemInvoiceNumbers)
                    ->where('movement_code', self::CIGAM_PURCHASE_ENTRY_CODE)
                    ->where('entry_exit', 'E')
                    ->whereIn('ref_size', $catalogBarcodes)
                    ->count();

                $debug['catalog_barcodes_matched_as_ref_size'] = $matchCount;
            }

            // Reverse: movement ref_sizes que existem como barcode em product_variants
            $movementRefSizes = Movement::query()
                ->whereIn('invoice_number', $itemInvoiceNumbers)
                ->where('movement_code', self::CIGAM_PURCHASE_ENTRY_CODE)
                ->where('entry_exit', 'E')
                ->whereNotNull('ref_size')
                ->distinct()
                ->pluck('ref_size')
                ->all();

            if (! empty($movementRefSizes)) {
                $reverseHits = ProductVariant::whereIn('barcode', $movementRefSizes)
                    ->with('product:id,reference')
                    ->limit(5)
                    ->get(['id', 'product_id', 'barcode', 'size_cigam_code']);

                $debug['reverse_lookup_hits'] = $reverseHits->count();
                $debug['reverse_lookup_sample'] = $reverseHits->map(fn ($v) => [
                    'variant_barcode' => $v->barcode,
                    'product_reference' => $v->product?->reference,
                    'product_id' => $v->product_id,
                ])->all();
            }
        }

        return $debug;
    }
}
