<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\ProductBrand;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderStatusHistory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa ordens de compra a partir de planilha XLSX/CSV no formato
 * legacy v1 (Meia Sola).
 *
 * ARQUITETURA (alinhada com v1 após feedback operacional):
 *
 *  - FORNECEDOR não é parte da ordem de compra. O v1 também não tem
 *    (a tabela `adms_purchase_order_controls` não tem supplier_id).
 *    Fornecedor só aparece em `order_payments`. Uma ordem de compra vira
 *    1+ order_payments vinculados a fornecedores (possivelmente diferentes).
 *
 *  - MARCA é obrigatória e vem de `product_brands` (sincronizado do CIGAM).
 *    Se a marca da planilha não existe em product_brands, a ordem inteira
 *    é rejeitada. NÃO criamos marcas automaticamente — o sync do CIGAM é
 *    a única fonte legítima.
 *
 *  - TAMANHOS usam de-para (PurchaseOrderSizeMapping) para traduzir labels
 *    da planilha (PP, 33, 33/34, 33.5) em product_sizes oficiais. Labels
 *    sem mapping fazem rejeitar o item individual (não a ordem inteira).
 *    Labels novos detectados durante import são auto-criados como pendentes
 *    no de-para (ensureLabelsExist) pra aparecer no CRUD.
 *
 * Formato da planilha (25 colunas fixas em PT-BR + N colunas de tamanho):
 *   Referência | Descrição | Material | Cor | Tipo | Grupo | Subgrupo |
 *   Marca | Estação | Coleção | Custo Unit | Preço Venda | Precif |
 *   Qtd Pedido | Custo total | Venda total | Nr Pedido | Status | Destino |
 *   Dt Pedido | Previsão | Pagamento | Nota fiscal | Emissão Nf | Confirmação
 *
 * Cada LINHA = 1 referência × N tamanhos (matriz horizontal).
 * Múltiplas linhas com o mesmo Nr Pedido são agrupadas em uma ordem única.
 */
class PurchaseOrderImportService
{
    public function __construct(
        protected PurchaseOrderSizeMappingService $sizeMapping,
    ) {}

    /** @var array<int, string> nomes normalizados das colunas fixas */
    private const FIXED_COLUMNS = [
        'referencia', 'descricao', 'material', 'cor',
        'tipo', 'grupo', 'subgrupo', 'marca',
        'estacao', 'colecao',
        'custo_unit', 'preco_venda', 'precif',
        'qtd_pedido', 'custo_total', 'venda_total',
        'nr_pedido', 'status', 'destino',
        'dt_pedido', 'previsao', 'pagamento',
        'nota_fiscal', 'emissao_nf', 'confirmacao',
    ];

    /** @var array<string, string> mapeia status PT-BR → value do enum */
    private const STATUS_MAP = [
        'pendente' => 'pending',
        'faturado' => 'invoiced',
        'faturado parcial' => 'partial_invoiced',
        'cancelado' => 'cancelled',
        'entregue' => 'delivered',
    ];

    /**
     * Preview — retorna metadados da planilha sem persistir nada.
     * Detecta brands e size labels pra que a UI mostre o que precisa ser
     * resolvido antes de permitir o import.
     *
     * @return array{
     *     rows: array, total: int,
     *     size_columns: array<int, string>,
     *     missing_columns: array<int, string>,
     *     brands_detected: array<int, array{name: string, product_brand_id: ?int, is_known: bool}>,
     *     sizes_pending: array<int, string>,
     *     can_import: bool
     * }
     */
    public function preview(string $filePath, int $limit = 10): array
    {
        $parsed = $this->readSpreadsheet($filePath);
        $rows = $parsed['rows'];
        $sizeColumns = $this->detectSizeColumns($parsed['headers']);
        $missing = $this->detectMissingColumns($parsed['headers']);

        // Detecta marcas únicas da planilha
        $brandNames = collect($rows)
            ->map(fn ($r) => trim((string) ($r['marca'] ?? '')))
            ->filter(fn ($n) => $n !== '')
            ->unique()
            ->values()
            ->all();

        // Resolve cada marca contra product_brands
        $knownBrands = ProductBrand::all()
            ->mapWithKeys(fn ($b) => [mb_strtolower(trim($b->name)) => $b->id])
            ->all();

        $brandsDetected = array_map(function ($name) use ($knownBrands) {
            $key = mb_strtolower($name);
            $id = $knownBrands[$key] ?? null;
            return [
                'name' => $name,
                'product_brand_id' => $id,
                'is_known' => $id !== null,
            ];
        }, $brandNames);

        // Ensure labels de tamanho existam no de-para (cria pendentes pra
        // o CRUD ser descobrível). E classifica resolvido vs pendente.
        $this->sizeMapping->ensureLabelsExist($sizeColumns);
        $sizeClassification = $this->sizeMapping->classify($sizeColumns);

        $unknownBrands = array_values(array_filter($brandsDetected, fn ($b) => ! $b['is_known']));
        $pendingSizes = $sizeClassification['pending'];

        // Bloqueia import se há marca desconhecida OU se NENHUMA coluna de
        // tamanho é resolvida (nada pra importar nesse caso). Se tem algumas
        // pendentes mas outras resolvidas, permite (itens pendentes serão
        // rejeitados individualmente).
        $canImport = empty($unknownBrands)
            && ! empty($sizeClassification['resolved'])
            && empty($missing);

        return [
            'rows' => array_slice($rows, 0, $limit),
            'total' => count($rows),
            'size_columns' => array_values($sizeColumns),
            'missing_columns' => $missing,
            'brands_detected' => array_values($brandsDetected),
            'sizes_pending' => $pendingSizes,
            'can_import' => $canImport,
        ];
    }

    /**
     * @return array{
     *     orders_created: int, orders_updated: int,
     *     items_created: int, items_updated: int,
     *     rows_processed: int, rows_rejected: int,
     *     items_rejected: int,
     *     rejected: array<int, array{row_number: int, reason: string, data: array}>
     * }
     */
    public function import(string $filePath, User $actor): array
    {
        $parsed = $this->readSpreadsheet($filePath);
        $rows = $parsed['rows'];
        $sizeColumns = $this->detectSizeColumns($parsed['headers']);

        $stats = [
            'orders_created' => 0,
            'orders_updated' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'rows_processed' => 0,
            'rows_rejected' => 0,
            'items_rejected' => 0,
            'rejected' => [],
        ];

        // Garante que labels da planilha existam no de-para (pra aparecerem
        // no CRUD) antes de começar a processar.
        $this->sizeMapping->ensureLabelsExist($sizeColumns);

        // Caches de lookup
        $storesByCode = Store::pluck('code', 'code')->all();
        $storesByName = Store::all()->mapWithKeys(fn ($s) => [mb_strtolower($s->name ?? '') => $s->code])->all();
        $brandsByName = ProductBrand::all()
            ->mapWithKeys(fn ($b) => [mb_strtolower(trim($b->name)) => $b->id])
            ->all();

        // Agrupa por Nr Pedido
        $byOrder = [];
        foreach ($rows as $idx => $row) {
            $orderNumber = trim((string) ($row['nr_pedido'] ?? ''));
            if ($orderNumber === '') {
                $stats['rows_rejected']++;
                $stats['rejected'][] = [
                    'row_number' => $idx + 2,
                    'reason' => 'Nr Pedido vazio',
                    'data' => $row,
                ];
                continue;
            }
            $byOrder[$orderNumber][] = ['row_number' => $idx + 2, 'data' => $row];
        }

        foreach ($byOrder as $orderNumber => $group) {
            $headerRow = $this->pickHeaderRow($group);
            $headerData = $headerRow['data'];
            $refLine = $headerRow['row_number'];

            // Resolve marca — OBRIGATÓRIA. Se não existe em product_brands, rejeita.
            $marcaName = trim((string) ($headerData['marca'] ?? ''));
            if ($marcaName === '') {
                $stats['rows_rejected'] += count($group);
                $stats['rejected'][] = [
                    'row_number' => $refLine,
                    'reason' => 'Marca vazia — coluna "Marca" obrigatória',
                    'data' => $headerData,
                ];
                continue;
            }

            $brandId = $brandsByName[mb_strtolower($marcaName)] ?? null;
            if ($brandId === null) {
                $stats['rows_rejected'] += count($group);
                $stats['rejected'][] = [
                    'row_number' => $refLine,
                    'reason' => "Marca '{$marcaName}' não cadastrada. Execute o sync do CIGAM.",
                    'data' => $headerData,
                ];
                continue;
            }

            // Resolve loja
            $storeCode = $this->resolveStore($headerData['destino'] ?? null, $storesByCode, $storesByName);
            if (! $storeCode) {
                $stats['rows_rejected'] += count($group);
                $stats['rejected'][] = [
                    'row_number' => $refLine,
                    'reason' => 'Destino não encontrado em stores: ' . ($headerData['destino'] ?? '(vazio)'),
                    'data' => $headerData,
                ];
                continue;
            }

            // Status do grupo: mais frequente
            $orderStatus = $this->resolveGroupStatus($group);

            try {
                DB::transaction(function () use (
                    $orderNumber, $headerData, $group, $brandId, $storeCode,
                    $orderStatus, $actor, $sizeColumns, &$stats
                ) {
                    $existing = PurchaseOrder::where('order_number', $orderNumber)->first();

                    $payload = [
                        'short_description' => $headerData['descricao'] ?? null,
                        'season' => trim((string) ($headerData['estacao'] ?? 'Sem estação')),
                        'collection' => trim((string) ($headerData['colecao'] ?? 'Sem coleção')),
                        'release_name' => 'Importação v1',
                        'supplier_id' => null, // alinhado com v1 — supplier só em order_payments
                        'store_id' => $storeCode,
                        'brand_id' => $brandId,
                        'order_date' => $this->parseDate($headerData['dt_pedido'] ?? null) ?? now()->toDateString(),
                        'predict_date' => $this->parseDate($headerData['previsao'] ?? null),
                        'payment_terms_raw' => $this->parsePaymentTerms($headerData['pagamento'] ?? null),
                        'notes' => sprintf(
                            "Importação de planilha v1.\nTipo: %s\nGrupo: %s\nSubgrupo: %s",
                            $headerData['tipo'] ?? '-',
                            $headerData['grupo'] ?? '-',
                            $headerData['subgrupo'] ?? '-'
                        ),
                        'updated_by_user_id' => $actor->id,
                    ];

                    if ($existing) {
                        if ($existing->status === PurchaseOrderStatus::PENDING) {
                            $existing->update($payload);
                            $stats['orders_updated']++;
                        }
                        $order = $existing;
                    } else {
                        $order = PurchaseOrder::create(array_merge($payload, [
                            'order_number' => $orderNumber,
                            'status' => $orderStatus->value,
                            'created_by_user_id' => $actor->id,
                        ]));
                        PurchaseOrderStatusHistory::create([
                            'purchase_order_id' => $order->id,
                            'from_status' => null,
                            'to_status' => $orderStatus->value,
                            'changed_by_user_id' => $actor->id,
                            'note' => 'Importada da planilha v1',
                            'created_at' => now(),
                        ]);
                        $stats['orders_created']++;
                    }

                    // Expande cada linha do grupo em items, um por tamanho com qty > 0
                    foreach ($group as $entry) {
                        $row = $entry['data'];
                        $reference = trim((string) ($row['referencia'] ?? ''));
                        if ($reference === '') {
                            continue;
                        }

                        $itemCommon = [
                            'description' => trim((string) ($row['descricao'] ?? '')),
                            'material' => $row['material'] ?? null,
                            'color' => $row['cor'] ?? null,
                            'group_name' => $row['grupo'] ?? null,
                            'subgroup_name' => $row['subgrupo'] ?? null,
                            'unit_cost' => $this->parseMoney($row['custo_unit'] ?? 0),
                            'selling_price' => $this->parseMoney($row['preco_venda'] ?? 0),
                            'pricing_locked' => $this->parsePricing($row['precif'] ?? null),
                            'invoice_number' => $row['nota_fiscal'] ?? null,
                            'invoice_emission_date' => $this->parseDate($row['emissao_nf'] ?? null),
                            'confirmation_date' => $this->parseDate($row['confirmacao'] ?? null),
                        ];

                        foreach ($sizeColumns as $sizeLabel) {
                            $qty = $this->parseQuantity($row[$sizeLabel] ?? null);
                            if ($qty <= 0) {
                                continue;
                            }

                            // Resolve label via de-para. Se pendente, rejeita o item.
                            $productSizeId = $this->sizeMapping->resolve($sizeLabel);
                            if ($productSizeId === null) {
                                $stats['items_rejected']++;
                                $stats['rejected'][] = [
                                    'row_number' => $entry['row_number'],
                                    'reason' => "Tamanho '{$sizeLabel}' sem mapeamento. Configure em Configurações → Tamanhos.",
                                    'data' => ['reference' => $reference, 'size' => $sizeLabel, 'qty' => $qty],
                                ];
                                continue;
                            }

                            $existingItem = PurchaseOrderItem::where('purchase_order_id', $order->id)
                                ->where('reference', $reference)
                                ->where('size', $sizeLabel)
                                ->first();

                            $itemData = array_merge($itemCommon, [
                                'quantity_ordered' => $qty,
                                'product_size_id' => $productSizeId,
                            ]);

                            if ($existingItem) {
                                $existingItem->update($itemData);
                                $stats['items_updated']++;
                            } else {
                                PurchaseOrderItem::create(array_merge($itemData, [
                                    'purchase_order_id' => $order->id,
                                    'reference' => $reference,
                                    'size' => $sizeLabel,
                                ]));
                                $stats['items_created']++;
                            }
                            $stats['rows_processed']++;
                        }
                    }
                });
            } catch (\Throwable $e) {
                $stats['rows_rejected'] += count($group);
                $stats['rejected'][] = [
                    'row_number' => $refLine,
                    'reason' => 'Erro: ' . $e->getMessage(),
                    'data' => $headerData,
                ];
            }
        }

        return $stats;
    }

    // ------------------------------------------------------------------
    // Spreadsheet reading — raw headers preservando "33/34", "33.5", etc
    // ------------------------------------------------------------------

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    protected function readSpreadsheet(string $filePath): array
    {
        $sheets = Excel::toArray(new class {}, $filePath);
        $sheet = $sheets[0] ?? [];
        if (empty($sheet)) {
            return ['headers' => [], 'rows' => []];
        }

        $rawHeaders = array_map(fn ($h) => $this->normalizeHeader((string) $h), $sheet[0]);
        $dataRows = array_slice($sheet, 1);

        $structured = [];
        foreach ($dataRows as $row) {
            if ($this->isRowEmpty($row)) {
                continue;
            }
            $assoc = [];
            foreach ($rawHeaders as $idx => $h) {
                if ($h === '') {
                    continue;
                }
                $assoc[$h] = $row[$idx] ?? null;
            }
            $structured[] = $assoc;
        }

        return ['headers' => array_filter($rawHeaders), 'rows' => $structured];
    }

    protected function normalizeHeader(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $noAccents = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
        if ($noAccents === false || $noAccents === '') {
            $noAccents = $this->stripAccents($raw);
        }

        $lower = strtolower($noAccents);
        $lower = preg_replace('/["\'`^~]/', '', $lower);
        $lower = preg_replace('/\s+/', '_', trim($lower));

        return $lower;
    }

    protected function stripAccents(string $str): string
    {
        $from = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ',
                 'Á','À','Ã','Â','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Õ','Ô','Ö','Ú','Ù','Û','Ü','Ç','Ñ'];
        $to   = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n',
                 'a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
        return str_replace($from, $to, $str);
    }

    protected function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && $cell !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param  array<int|string, string>  $headers
     * @return array<int, string>
     */
    protected function detectSizeColumns(array $headers): array
    {
        $headers = array_values(array_filter($headers));
        $fixed = array_flip(self::FIXED_COLUMNS);
        return array_values(array_filter($headers, fn ($h) => ! isset($fixed[$h])));
    }

    /**
     * @return array<int, string>
     */
    protected function detectMissingColumns(array $headers): array
    {
        $required = ['referencia', 'descricao', 'marca', 'estacao', 'colecao', 'nr_pedido', 'destino', 'dt_pedido'];
        $present = array_flip(array_values(array_filter($headers)));
        return array_values(array_filter($required, fn ($col) => ! isset($present[$col])));
    }

    // ------------------------------------------------------------------
    // Row-level helpers
    // ------------------------------------------------------------------

    protected function pickHeaderRow(array $group): array
    {
        foreach ($group as $entry) {
            $status = mb_strtolower(trim((string) ($entry['data']['status'] ?? '')));
            if ($status !== 'cancelado') {
                return $entry;
            }
        }
        return $group[0];
    }

    protected function resolveGroupStatus(array $group): PurchaseOrderStatus
    {
        $counts = [];
        $firstSeen = [];
        foreach ($group as $entry) {
            $raw = mb_strtolower(trim((string) ($entry['data']['status'] ?? 'pendente')));
            $enumValue = self::STATUS_MAP[$raw] ?? 'pending';
            $counts[$enumValue] = ($counts[$enumValue] ?? 0) + 1;
            if (! isset($firstSeen[$enumValue])) {
                $firstSeen[$enumValue] = count($firstSeen);
            }
        }

        uksort($counts, function ($a, $b) use ($counts, $firstSeen) {
            if ($counts[$a] !== $counts[$b]) {
                return $counts[$b] - $counts[$a];
            }
            return $firstSeen[$a] - $firstSeen[$b];
        });

        $winner = array_key_first($counts);
        return PurchaseOrderStatus::from($winner);
    }

    protected function resolveStore($destino, array $byCode, array $byName): ?string
    {
        $val = trim((string) $destino);
        if ($val === '') {
            return null;
        }

        if (isset($byCode[$val])) {
            return $byCode[$val];
        }

        $lower = mb_strtolower($val);
        return $byName[$lower] ?? null;
    }

    protected function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                    ->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $str, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if ($year < 100) {
                $year += 2000;
            }
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
            return null;
        }

        try {
            return \Carbon\Carbon::parse($str)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function parseMoney($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        $str = trim((string) $value);

        if (preg_match('/,\d{1,2}$/', $str)) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        }

        return (float) $str;
    }

    protected function parseQuantity($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $str = preg_replace('/\D/', '', (string) $value);
        return $str === '' ? 0 : (int) $str;
    }

    protected function parsePricing($value): bool
    {
        if ($value === null) {
            return false;
        }
        $str = mb_strtolower(trim((string) $value));
        return in_array($str, ['ok', 'sim', 'yes', '1', 'true'], true);
    }

    protected function parsePaymentTerms($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return trim((string) $value);
    }
}
