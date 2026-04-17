<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\ProductBrand;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderStatusHistory;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

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
        protected PurchaseOrderBrandAliasService $brandAliasService,
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
     *     can_import: bool,
     *     header_line: int,
     *     headers_detected: array<int, string>
     * }
     */
    public function preview(string $filePath, int $limit = 10): array
    {
        $parsed = $this->readSpreadsheet($filePath);
        $rows = $parsed['rows'];
        $sizeColumns = $this->detectSizeColumns($parsed['headers']);
        $ignoredColumns = $this->detectIgnoredColumns($parsed['headers']);
        $missing = $this->detectMissingColumns($parsed['headers']);

        // Detecta marcas únicas da planilha
        $brandNames = collect($rows)
            ->map(fn ($r) => trim((string) ($r['marca'] ?? '')))
            ->filter(fn ($n) => $n !== '')
            ->unique()
            ->values()
            ->all();

        // Garante que marcas novas (não conhecidas nem com alias) aparecem
        // no CRUD como pendentes — assim o usuário pode criar aliases direto
        $this->brandAliasService->ensureNamesExist($brandNames);

        // Classifica marcas em 3 grupos: known (match direto), aliased
        // (resolvidas via alias), unknown (sem match)
        $brandClassification = $this->brandAliasService->classify($brandNames);

        // Mantém o formato antigo brandsDetected pra compatibilidade com UI
        $brandsDetected = [];
        foreach ($brandClassification['known'] as $b) {
            $brandsDetected[] = [
                'name' => $b['name'],
                'product_brand_id' => $b['product_brand_id'],
                'is_known' => true,
                'resolved_via' => 'direct',
            ];
        }
        foreach ($brandClassification['aliased'] as $b) {
            $brandsDetected[] = [
                'name' => $b['name'],
                'product_brand_id' => $b['product_brand_id'],
                'product_brand_name' => $b['product_brand_name'],
                'is_known' => true, // considerado "resolvido" pela UI
                'resolved_via' => 'alias',
                // Alias pra UI que já usa esse nome
                'resolved_to_name' => $b['product_brand_name'],
            ];
        }
        foreach ($brandClassification['unknown'] as $name) {
            $brandsDetected[] = [
                'name' => $name,
                'product_brand_id' => null,
                'is_known' => false,
                'resolved_via' => null,
            ];
        }

        // Ensure labels de tamanho existam no de-para (cria pendentes pra
        // o CRUD ser descobrível). E classifica resolvido vs pendente.
        $this->sizeMapping->ensureLabelsExist($sizeColumns);
        $sizeClassification = $this->sizeMapping->classify($sizeColumns);

        $unknownBrands = array_values(array_filter($brandsDetected, fn ($b) => ! $b['is_known']));
        $aliasedBrands = array_values(array_filter($brandsDetected, fn ($b) => ($b['resolved_via'] ?? null) === 'alias'));
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
            'ignored_columns' => array_values($ignoredColumns),
            'missing_columns' => $missing,
            'brands_detected' => array_values($brandsDetected),
            'brands_aliased' => $aliasedBrands,
            'sizes_pending' => $pendingSizes,
            'can_import' => $canImport,
            'header_line' => $parsed['header_line'] ?? 1,
            'headers_detected' => array_values($parsed['headers']),
            'sheet_name' => $parsed['sheet_name'] ?? null,
            'sheet_names' => $parsed['sheet_names'] ?? [],
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
            // Lista detalhada — limitada a 100 pra não explodir session flash
            'rejected' => [],
            // Agregado por motivo (essencial pra debug de imports grandes)
            'rejected_reasons' => [], // ['motivo' => count]
        ];

        // Garante que labels e nomes de marca da planilha existam nos
        // CRUDs (pra aparecerem como pendentes) antes de começar a processar.
        $this->sizeMapping->ensureLabelsExist($sizeColumns);

        $uniqueBrandNames = collect($rows)
            ->map(fn ($r) => trim((string) ($r['marca'] ?? '')))
            ->filter(fn ($n) => $n !== '')
            ->unique()
            ->values()
            ->all();
        $this->brandAliasService->ensureNamesExist($uniqueBrandNames);

        // Caches de lookup
        $storesByCode = Store::pluck('code', 'code')->all();
        $storesByName = Store::all()->mapWithKeys(fn ($s) => [mb_strtolower($s->name ?? '') => $s->code])->all();

        // Cache de products por reference — vincula purchase_order_items ao catálogo.
        // Coleta as references únicas da planilha pra fazer um único WHERE IN
        // (em vez de carregar os 355k+ products inteiros em memória).
        $uniqueRefs = collect($rows)
            ->map(fn ($r) => trim((string) ($r['referencia'] ?? '')))
            ->filter(fn ($r) => $r !== '')
            ->unique()
            ->values()
            ->all();
        // mapWithKeys com cast (string) evita que PHP converta references
        // numéricas (ex: "103200") pra int como chave de array — quebra o
        // lookup se a reference tiver hífen ("10330034-288") ou leading zeros.
        $productsByRef = ! empty($uniqueRefs)
            ? Product::whereIn('reference', $uniqueRefs)
                ->get(['id', 'reference'])
                ->mapWithKeys(fn ($p) => [(string) $p->reference => $p->id])
                ->all()
            : [];

        // Debug: loga as primeiras 5 linhas pra diagnóstico de imports
        // que falham silenciosamente (0 criadas, N rejeitadas). Mostra
        // o estado PÓS forward-fill pra confirmar que os campos de
        // cabeçalho estão sendo propagados.
        $debugSample = array_slice($rows, 0, min(5, count($rows)));
        Log::info('PurchaseOrder import: first 5 rows after forward-fill', [
            'total_rows' => count($rows),
            'sample' => array_map(function ($row) {
                return [
                    'nr_pedido' => $row['nr_pedido'] ?? '(MISSING KEY)',
                    'marca' => $row['marca'] ?? '(MISSING KEY)',
                    'destino' => $row['destino'] ?? '(MISSING KEY)',
                    'referencia' => $row['referencia'] ?? '(MISSING KEY)',
                    'estacao' => $row['estacao'] ?? '(MISSING KEY)',
                    'status' => $row['status'] ?? '(MISSING KEY)',
                    'all_keys' => array_keys($row),
                    'first_10_values' => array_slice(array_values($row), 0, 10),
                ];
            }, $debugSample),
        ]);

        // Agrupa por Nr Pedido
        $byOrder = [];
        foreach ($rows as $idx => $row) {
            $orderNumber = trim((string) ($row['nr_pedido'] ?? ''));
            if ($orderNumber === '') {
                $this->pushRejected($stats, $idx + 2, 'Nr Pedido vazio', $row);
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
                $this->pushRejected($stats, $refLine, 'Marca vazia — coluna "Marca" obrigatória', $headerData, count($group));
                continue;
            }

            // Resolve via BrandAliasService: tenta match direto em
            // product_brands primeiro, depois alias ativo resolvido
            $brandId = $this->brandAliasService->resolve($marcaName);
            if ($brandId === null) {
                $this->pushRejected(
                    $stats,
                    $refLine,
                    "Marca '{$marcaName}' não cadastrada e sem alias resolvido",
                    $headerData,
                    count($group)
                );
                continue;
            }

            // Resolve loja
            $storeCode = $this->resolveStore($headerData['destino'] ?? null, $storesByCode, $storesByName);
            if (! $storeCode) {
                $destinoStr = trim((string) ($headerData['destino'] ?? ''));
                $this->pushRejected(
                    $stats,
                    $refLine,
                    'Destino não encontrado em stores: ' . ($destinoStr ?: '(vazio)'),
                    $headerData,
                    count($group)
                );
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
                            // Vincula ao catálogo de produtos por reference
                            'product_id' => $productsByRef[$reference] ?? null,
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
                                $genericReason = 'Tamanho sem mapeamento';
                                $stats['rejected_reasons'][$genericReason] =
                                    ($stats['rejected_reasons'][$genericReason] ?? 0) + 1;
                                if (count($stats['rejected']) < 100) {
                                    $stats['rejected'][] = [
                                        'row_number' => $entry['row_number'],
                                        'reason' => "Tamanho '{$sizeLabel}' sem mapeamento",
                                        'data' => ['reference' => $reference, 'size' => $sizeLabel, 'qty' => $qty],
                                    ];
                                }
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
                $this->pushRejected(
                    $stats,
                    $refLine,
                    'Erro: ' . $e->getMessage(),
                    $headerData,
                    count($group)
                );
            }
        }

        // Ordena rejected_reasons por contagem desc pra facilitar debug
        arsort($stats['rejected_reasons']);

        // Log do resumo pra diagnóstico
        Log::info('PurchaseOrder import: finished', [
            'orders_created' => $stats['orders_created'],
            'orders_updated' => $stats['orders_updated'],
            'items_created' => $stats['items_created'],
            'rows_rejected' => $stats['rows_rejected'],
            'items_rejected' => $stats['items_rejected'],
            'rejected_reasons' => $stats['rejected_reasons'],
            'byOrder_count' => count($byOrder),
            'first_3_rejected' => array_slice($stats['rejected'], 0, 3),
        ]);

        return $stats;
    }

    /**
     * Normaliza adição de rejeição: incrementa contador agregado por
     * motivo + mantém só as primeiras 100 no detail list pra economizar
     * memória em imports grandes (planilhas históricas podem ter 20k+
     * linhas rejeitadas).
     */
    protected function pushRejected(array &$stats, int $rowNumber, string $reason, array $data, int $count = 1): void
    {
        $stats['rows_rejected'] += $count;

        // Agrupa por motivo normalizado (sem detalhes específicos de valor)
        // pra ver "top reasons". Normaliza marca/loja específica numa chave genérica.
        $genericReason = preg_replace('/\'([^\']+)\'/', '*', $reason);
        $genericReason = preg_replace('/: .+$/', '', $genericReason);
        $stats['rejected_reasons'][$genericReason] = ($stats['rejected_reasons'][$genericReason] ?? 0) + $count;

        // Mantém só as primeiras 100 no detail pra session flash não estourar
        if (count($stats['rejected']) < 100) {
            $stats['rejected'][] = [
                'row_number' => $rowNumber,
                'reason' => $reason,
                'data' => $data,
            ];
        }
    }

    // ------------------------------------------------------------------
    // Spreadsheet reading — raw headers preservando "33/34", "33.5", etc
    // ------------------------------------------------------------------

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>, header_line: int, sheet_name: ?string, sheet_names: array<int, string>}
     */
    protected function readSpreadsheet(string $filePath): array
    {
        // Usa PhpSpreadsheet direto (não via maatwebsite/excel) pra ter
        // comportamento previsível e controle total sobre a leitura.
        try {
            $reader = IOFactory::createReaderForFile($filePath);

            if ($reader instanceof CsvReader) {
                $reader->setInputEncoding('UTF-8');
            }

            $reader->setReadDataOnly(true); // ignora formatação, mais rápido
            $spreadsheet = $reader->load($filePath);
        } catch (\Throwable $e) {
            Log::error('PurchaseOrder readSpreadsheet failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            return [
                'headers' => [], 'rows' => [], 'header_line' => 0,
                'sheet_name' => null, 'sheet_names' => [],
            ];
        }

        // Planilhas corporativas frequentemente têm múltiplas abas (pivot de
        // resumo, gráficos, dados brutos). Em vez de usar getActiveSheet()
        // cegamente, varremos TODAS as abas procurando aquela cujo header
        // bate melhor com as FIXED_COLUMNS (score = quantas colunas reconhecidas).
        $sheetNames = $spreadsheet->getSheetNames();

        $bestSheet = null;
        $bestSheetName = null;
        $bestHeaderIdx = 0;
        $bestScore = 0;

        foreach ($sheetNames as $name) {
            $ws = $spreadsheet->getSheetByName($name);
            if ($ws === null) {
                continue;
            }
            $rows = $ws->toArray(null, true, true, false);
            if (empty($rows)) {
                continue;
            }

            // Procura a melhor linha de header nas primeiras 10 linhas da aba
            $maxCheck = min(10, count($rows));
            for ($i = 0; $i < $maxCheck; $i++) {
                if ($this->isRowEmpty($rows[$i])) {
                    continue;
                }
                $normalized = array_map(fn ($h) => $this->normalizeHeader((string) $h), $rows[$i]);
                $score = count(array_intersect($normalized, self::FIXED_COLUMNS));

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestSheet = $rows;
                    $bestSheetName = $name;
                    $bestHeaderIdx = $i;
                }
            }
        }

        // Se nenhuma aba bateu o threshold (>= 3 colunas reconhecidas),
        // usa a aba ativa como fallback
        if ($bestSheet === null || $bestScore < 3) {
            $active = $spreadsheet->getActiveSheet();
            $bestSheet = $active->toArray(null, true, true, false);
            $bestSheetName = $active->getTitle();
            $bestHeaderIdx = 0;
        }

        if (empty($bestSheet)) {
            Log::warning('PurchaseOrder readSpreadsheet: empty workbook', ['file' => $filePath]);
            return [
                'headers' => [], 'rows' => [], 'header_line' => 0,
                'sheet_name' => $bestSheetName, 'sheet_names' => $sheetNames,
            ];
        }

        $rawHeaders = array_map(fn ($h) => $this->normalizeHeader((string) $h), $bestSheet[$bestHeaderIdx] ?? []);
        $dataRows = array_slice($bestSheet, $bestHeaderIdx + 1);

        // Forward fill: planilhas corporativas frequentemente usam merged
        // cells OU deixam campos de cabeçalho vazios em linhas secundárias
        // do mesmo pedido, assumindo que "herda" da linha anterior. O
        // PhpSpreadsheet converte merged cells em valor na top-left +
        // vazio nas demais. Aplicamos forward fill nos campos que
        // tipicamente pertencem ao CABEÇALHO do pedido (não variam
        // entre itens do mesmo Nr Pedido).
        $dataRows = $this->forwardFillHeaderFields($dataRows, $rawHeaders);

        // Debug: se score final continua baixo, loga amostra pra diagnóstico
        if ($bestScore < 5) {
            Log::warning('PurchaseOrder readSpreadsheet: few recognized headers', [
                'file' => $filePath,
                'sheet_name' => $bestSheetName,
                'all_sheet_names' => $sheetNames,
                'total_rows' => count($bestSheet),
                'header_line' => $bestHeaderIdx + 1,
                'recognized_fixed_columns' => $bestScore,
                'first_5_rows_preview' => array_map(
                    fn ($row) => array_slice(array_map(fn ($c) => mb_substr((string) $c, 0, 40), $row ?? []), 0, 30),
                    array_slice($bestSheet, 0, 5)
                ),
                'normalized_headers' => array_values($rawHeaders),
            ]);
        }

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

        return [
            'headers' => array_filter($rawHeaders),
            'rows' => $structured,
            'header_line' => $bestHeaderIdx + 1, // 1-indexed pra usuário
            'sheet_name' => $bestSheetName,
            'sheet_names' => $sheetNames,
        ];
    }

    /**
     * Campos de cabeçalho do pedido que NÃO variam entre itens do mesmo
     * Nr Pedido. Se uma linha tem valor vazio pra um desses campos, herda
     * da linha anterior (forward fill).
     *
     * Campos NÃO incluídos (variam por item):
     *  - referencia, descricao, material, cor — específicos do produto
     *  - custo_unit, preco_venda, precif — específicos do item
     *  - qtd_pedido, custo_total, venda_total — derivados do item
     *  - Tamanhos (PP, 33, 34, etc) — quantidades por tamanho
     */
    private const FORWARD_FILL_FIELDS = [
        'nr_pedido', 'status', 'destino',
        'marca', 'estacao', 'colecao',
        'dt_pedido', 'previsao', 'pagamento',
        'tipo', 'grupo', 'subgrupo',
        'nota_fiscal', 'emissao_nf', 'confirmacao',
    ];

    /**
     * Aplica forward fill nos campos de cabeçalho do pedido. Planilhas
     * corporativas frequentemente usam merged cells OU deixam campos de
     * cabeçalho vazios em linhas secundárias do mesmo pedido, assumindo
     * que "herda" da linha anterior. Sem este fill, a maioria das linhas
     * seria rejeitada por "Nr Pedido vazio".
     *
     * @param  array<int, array>  $dataRows
     * @param  array<int, string>  $rawHeaders
     * @return array<int, array>
     */
    protected function forwardFillHeaderFields(array $dataRows, array $rawHeaders): array
    {
        if (empty($dataRows) || empty($rawHeaders)) {
            return $dataRows;
        }

        // Mapeia nome do campo → índice da coluna
        $fieldIndex = [];
        foreach ($rawHeaders as $idx => $h) {
            if ($h === '') {
                continue;
            }
            $fieldIndex[$h] = $idx;
        }

        $forwardFillIndices = [];
        foreach (self::FORWARD_FILL_FIELDS as $field) {
            if (isset($fieldIndex[$field])) {
                $forwardFillIndices[$field] = $fieldIndex[$field];
            }
        }

        if (empty($forwardFillIndices)) {
            return $dataRows;
        }

        // lastValues[$colIdx] = último valor não-vazio visto
        $lastValues = [];

        foreach ($dataRows as &$row) {
            // Linha inteira vazia: resetar é arriscado (pode ser separador
            // que não indica mudança de pedido). Deixa last como está.
            if ($this->isRowEmpty($row)) {
                continue;
            }

            foreach ($forwardFillIndices as $colIdx) {
                $cell = $row[$colIdx] ?? null;
                if ($cell !== null && trim((string) $cell) !== '') {
                    $lastValues[$colIdx] = $cell;
                } elseif (isset($lastValues[$colIdx])) {
                    $row[$colIdx] = $lastValues[$colIdx];
                }
            }
        }
        unset($row);

        return $dataRows;
    }

    /**
     * Detecta qual linha da planilha é o header real, procurando pela
     * primeira linha (nas primeiras 10) que contém pelo menos 3 colunas
     * conhecidas da lista FIXED_COLUMNS.
     *
     * Se nenhuma linha bater o threshold, retorna 0 (comportamento legacy
     * — usa a primeira linha).
     */
    protected function detectHeaderLine(array $sheet): int
    {
        $maxCheck = min(10, count($sheet));
        $bestIdx = 0;
        $bestScore = 0;

        for ($i = 0; $i < $maxCheck; $i++) {
            if (empty($sheet[$i]) || $this->isRowEmpty($sheet[$i])) {
                continue;
            }

            $normalized = array_map(fn ($h) => $this->normalizeHeader((string) $h), $sheet[$i]);
            $score = count(array_intersect($normalized, self::FIXED_COLUMNS));

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIdx = $i;
            }
        }

        // Exige pelo menos 3 colunas conhecidas pra trocar o default.
        // Abaixo disso, fica na linha 0 e deixa a lógica de missing_columns
        // avisar o usuário com clareza.
        return $bestScore >= 3 ? $bestIdx : 0;
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
     * Detecta colunas de tamanho no header. Precisa satisfazer 2 condições:
     *  1. NÃO estar na lista FIXED_COLUMNS (não é campo de cabeçalho conhecido)
     *  2. Passar no looksLikeSizeLabel() — o label precisa parecer com um
     *     tamanho válido (PP, M, 33, 33.5, 33/34, 70, etc)
     *
     * Labels fora desses dois critérios (ex: "Observação", "Fornecedor",
     * "FATURADO" — se aparecem como colunas por causa de layout exótico da
     * planilha) são ignoradas silenciosamente em vez de virarem "tamanhos
     * pendentes" no de-para. Isso evita poluir o CRUD com labels que não
     * são tamanhos de verdade.
     *
     * @param  array<int|string, string>  $headers
     * @return array<int, string>
     */
    protected function detectSizeColumns(array $headers): array
    {
        $headers = array_values(array_filter($headers));
        $fixed = array_flip(self::FIXED_COLUMNS);

        return array_values(array_filter($headers, function ($h) use ($fixed) {
            if (isset($fixed[$h])) {
                return false; // campo fixo conhecido, não é tamanho
            }
            return $this->looksLikeSizeLabel($h);
        }));
    }

    /**
     * Retorna as colunas que não são FIXED nem tamanhos válidos. Útil pra
     * preview exibir "essas colunas da planilha foram ignoradas" e dar
     * transparência do parsing.
     *
     * @param  array<int|string, string>  $headers
     * @return array<int, string>
     */
    protected function detectIgnoredColumns(array $headers): array
    {
        $headers = array_values(array_filter($headers));
        $fixed = array_flip(self::FIXED_COLUMNS);

        return array_values(array_filter($headers, function ($h) use ($fixed) {
            if (isset($fixed[$h])) {
                return false; // campo fixo é usado, não ignorado
            }
            return ! $this->looksLikeSizeLabel($h);
        }));
    }

    /**
     * Valida se um label parece um tamanho de produto legítimo.
     *
     * Aceita:
     *  - Vestuário: PP, P, M, G, GG, XG, XGG, XXG, XXGG, XXGGG
     *  - Legado: 01
     *  - Numéricos: 33, 40, 70, 105 (1-3 dígitos)
     *  - Meio-tamanhos: 33.5, 34.5, 35.5, 36.5
     *  - Duplos: 33/34, 35/36, 37/38, 39/40
     *
     * Rejeita: FATURADO, ENTREGUE, CANCELADO, Observação, etc.
     */
    protected function looksLikeSizeLabel(string $label): bool
    {
        $upper = mb_strtoupper(trim($label));
        if ($upper === '') {
            return false;
        }

        // Vestuário: PP, P, M, G, GG, XG, XXG, XGG, XXGG
        if (preg_match('/^X{0,2}(P{1,2}|M|G{1,2})$/', $upper)) {
            return true;
        }

        // Legado numérico pequeno: "01"
        if ($upper === '01') {
            return true;
        }

        // Numérico puro: 32, 33, 40, 70, 105 (1 a 3 dígitos)
        if (preg_match('/^\d{1,3}$/', $upper)) {
            return true;
        }

        // Meio-tamanho: 33.5, 34.5 (com ponto ou vírgula decimal)
        if (preg_match('/^\d{1,3}[.,]\d$/', $upper)) {
            return true;
        }

        // Numérico duplo: 33/34, 35/36
        if (preg_match('/^\d{1,3}\/\d{1,3}$/', $upper)) {
            return true;
        }

        return false;
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

        // 1. Match exato por code (ex: "Z424")
        if (isset($byCode[$val])) {
            return $byCode[$val];
        }

        // 2. Match exato por name (case-insensitive)
        $lower = mb_strtolower($val);
        if (isset($byName[$lower])) {
            return $byName[$lower];
        }

        // 3. Match normalizado — remove hífens, pontuação e espaços extras.
        //    Resolve variações tipo "CD MEIA SOLA" vs "CD - Meia Sola"
        $normalized = $this->normalizeStoreName($val);
        foreach ($byName as $name => $code) {
            if ($this->normalizeStoreName($name) === $normalized) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Normaliza nome de loja pra comparação fuzzy:
     * - lowercase
     * - remove hífens, pontos, vírgulas
     * - colapsa espaços múltiplos em um
     * - trim
     *
     * "CD - Meia Sola" → "cd meia sola"
     * "CD MEIA SOLA"   → "cd meia sola"
     */
    protected function normalizeStoreName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[\-\.\,\/]/', ' ', $name); // remove pontuação
        $name = preg_replace('/\s+/', ' ', $name);        // colapsa espaços
        return trim($name);
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
