<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\Brand;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderStatusHistory;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa ordens de compra a partir de planilha XLSX/CSV no formato
 * legacy v1 (Meia Sola).
 *
 * Formato da planilha:
 *
 * Colunas fixas (nesta ordem, mas posicionalmente flexível — lookup é por nome):
 *   Referência | Descrição | Material | Cor | Tipo | Grupo | Subgrupo |
 *   Marca | Estação | Coleção | Custo Unit | Preço Venda | Precif |
 *   Qtd Pedido | Custo total | Venda total | Nr Pedido | Status | Destino |
 *   Dt Pedido | Previsão | Pagamento | Nota fiscal | Emissão Nf | Confirmação
 *
 * Colunas de tamanho (qualquer nome que não esteja na lista fixa): PP, P, M, G, GG,
 * 01, 33–40, 33/34, 35/36, 37/38, 39/40, 33.5–39.5, 70–105, etc.
 *
 * Cada LINHA = 1 referência × N tamanhos (matriz horizontal).
 * Múltiplas linhas com o mesmo Nr Pedido são agrupadas em uma ordem única.
 *
 * Regras de import:
 *  - Cabeçalho da ordem: usa dados da primeira linha não-cancelada do grupo
 *  - Status: mais frequente entre as linhas do grupo (mapeado PT → enum)
 *  - Fornecedor: exige $defaultSupplierId no upload (planilha v1 não tem fornecedor)
 *  - Loja: lookup por code OU name (case-insensitive)
 *  - Marca: lookup por name; se não achar, marca fica null (não rejeita)
 *  - Datas: parser tolerante — dd/mm/yyyy, ISO, ou Excel serial
 *  - Valores: aceita "172,90" (BR) ou "172.90"
 *
 * Upsert por:
 *  - Cabeçalho: (order_number)
 *  - Item: (purchase_order_id, reference, size) — UNIQUE index da Fase 1
 *
 * Headers são lidos brutos (sem slug formatter) pra preservar colunas
 * tipo "33/34" e "33.5" que o slug quebraria.
 */
class PurchaseOrderImportService
{
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
     * Preview — retorna as primeiras N linhas estruturadas sem persistir.
     *
     * @return array{rows: array, total: int, size_columns: array, missing_columns: array}
     */
    public function preview(string $filePath, int $limit = 10): array
    {
        $parsed = $this->readSpreadsheet($filePath);
        $rows = $parsed['rows'];

        $sizeColumns = $this->detectSizeColumns($parsed['headers']);
        $missing = $this->detectMissingColumns($parsed['headers']);

        return [
            'rows' => array_slice($rows, 0, $limit),
            'total' => count($rows),
            'size_columns' => array_values($sizeColumns),
            'missing_columns' => $missing,
        ];
    }

    /**
     * @return array{
     *     orders_created: int, orders_updated: int,
     *     items_created: int, items_updated: int,
     *     rows_processed: int, rows_rejected: int,
     *     rejected: array<int, array{row_number: int, reason: string, data: array}>
     * }
     */
    public function import(string $filePath, User $actor, ?int $defaultSupplierId = null): array
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
            'rejected' => [],
        ];

        // Valida que temos fornecedor default (planilha v1 não traz fornecedor)
        $supplier = $defaultSupplierId ? Supplier::find($defaultSupplierId) : null;
        if (! $supplier) {
            $stats['rejected'][] = [
                'row_number' => 0,
                'reason' => 'Fornecedor padrão não informado ou inválido. Selecione um fornecedor antes de importar.',
                'data' => [],
            ];
            $stats['rows_rejected'] = count($rows);
            return $stats;
        }

        // Caches de lookup
        $storesByCode = Store::pluck('code', 'code')->all();
        $storesByName = Store::all()->mapWithKeys(fn ($s) => [mb_strtolower($s->name ?? '') => $s->code])->all();
        $brandsByName = Brand::all()->mapWithKeys(fn ($b) => [mb_strtolower($b->name ?? '') => $b->id])->all();

        // Agrupa por Nr Pedido (order_number)
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
            // Escolhe linha de referência: primeira não-cancelada, ou primeira
            $headerRow = $this->pickHeaderRow($group);
            $headerData = $headerRow['data'];
            $refLine = $headerRow['row_number'];

            // Resolve loja
            $storeCode = $this->resolveStore(
                $headerData['destino'] ?? null,
                $storesByCode,
                $storesByName
            );
            if (! $storeCode) {
                $stats['rows_rejected'] += count($group);
                $stats['rejected'][] = [
                    'row_number' => $refLine,
                    'reason' => 'Destino não encontrado em stores: ' . ($headerData['destino'] ?? '(vazio)'),
                    'data' => $headerData,
                ];
                continue;
            }

            // Resolve marca (opcional — se não achar, fica null)
            $brandId = null;
            $marcaName = trim((string) ($headerData['marca'] ?? ''));
            if ($marcaName !== '') {
                $brandId = $brandsByName[mb_strtolower($marcaName)] ?? null;
            }

            // Status do grupo: mais frequente
            $orderStatus = $this->resolveGroupStatus($group);

            try {
                DB::transaction(function () use (
                    $orderNumber, $headerData, $group, $supplier, $storeCode, $brandId,
                    $orderStatus, $actor, $sizeColumns, &$stats
                ) {
                    $existing = PurchaseOrder::where('order_number', $orderNumber)->first();

                    $payload = [
                        'short_description' => $headerData['descricao'] ?? null,
                        'season' => trim((string) ($headerData['estacao'] ?? 'Sem estação')),
                        'collection' => trim((string) ($headerData['colecao'] ?? 'Sem coleção')),
                        'release_name' => 'Importação v1',
                        'supplier_id' => $supplier->id,
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
                        // Só reatualiza cabeçalho se ainda pendente
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

                    // Expande cada linha do grupo em N items (1 por tamanho com qty > 0)
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

                            $existingItem = PurchaseOrderItem::where('purchase_order_id', $order->id)
                                ->where('reference', $reference)
                                ->where('size', $sizeLabel)
                                ->first();

                            $itemData = array_merge($itemCommon, [
                                'quantity_ordered' => $qty,
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
        // Usa Excel::toArray com importer anônimo — pega a matriz bruta
        // sem passar pelo HeadingRowFormatter do WithHeadingRow (que quebraria
        // "33/34" → "3334")
        $sheets = Excel::toArray(new class {
            // Marker class vazio
        }, $filePath);

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

    /**
     * Normaliza um header:
     *  - remove acentos
     *  - lowercase
     *  - trim
     *  - espaços → underscore
     *  - preserva / e . (necessário pra tamanhos tipo "33/34" e "33.5")
     */
    protected function normalizeHeader(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Remove acentos (iconv pode falhar em chars não-mapeáveis — fallback manual)
        $noAccents = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
        if ($noAccents === false || $noAccents === '') {
            $noAccents = $this->stripAccents($raw);
        }

        $lower = strtolower($noAccents);
        // Limpa caracteres bizarros do iconv (~^'` etc)
        $lower = preg_replace('/["\'`^~]/', '', $lower);
        // Espaços viram underscore
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
     * Retorna os nomes das colunas de tamanho (tudo que não está em FIXED_COLUMNS).
     *
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
     * Retorna quais colunas fixas essenciais estão faltando no header.
     *
     * @return array<int, string>
     */
    protected function detectMissingColumns(array $headers): array
    {
        $required = ['referencia', 'descricao', 'estacao', 'colecao', 'nr_pedido', 'destino', 'dt_pedido'];
        $present = array_flip(array_values(array_filter($headers)));
        return array_values(array_filter($required, fn ($col) => ! isset($present[$col])));
    }

    // ------------------------------------------------------------------
    // Row-level helpers
    // ------------------------------------------------------------------

    /**
     * Seleciona a linha que representa melhor o cabeçalho do grupo.
     * Preferência: primeira linha não-cancelada; fallback: primeira.
     */
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

    /**
     * Status da ordem = mais frequente do grupo, mapeado PT → enum.
     * Empate vai pro primeiro encontrado.
     */
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

        // Ordena por count desc, depois por first-seen asc (estabilidade)
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

        // Match exato por code (ex: "Z424")
        if (isset($byCode[$val])) {
            return $byCode[$val];
        }

        // Match por name (case-insensitive, ex: "CD MEIA SOLA")
        $lower = mb_strtolower($val);
        return $byName[$lower] ?? null;
    }

    protected function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Serial numeric do Excel
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

        // Formato brasileiro dd/mm/yyyy
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

        // ISO ou outros formatos reconhecíveis pelo Carbon
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

        // Formato BR com vírgula decimal: "1.234,56" ou "172,90"
        if (preg_match('/,\d{1,2}$/', $str)) {
            $str = str_replace('.', '', $str); // remove separador de milhar
            $str = str_replace(',', '.', $str); // vírgula → ponto decimal
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

    /**
     * "Precif" = "OK" significa que o preço foi confirmado → pricing_locked=true.
     */
    protected function parsePricing($value): bool
    {
        if ($value === null) {
            return false;
        }
        $str = mb_strtolower(trim((string) $value));
        return in_array($str, ['ok', 'sim', 'yes', '1', 'true'], true);
    }

    /**
     * "Pagamento" na planilha v1 costuma ser um único número de dias ("120")
     * ou uma lista separada por barra/espaço. Preservamos a string como está
     * — o PaymentTermsParser (Fase 3) consome esse formato na auto-geração.
     */
    protected function parsePaymentTerms($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return trim((string) $value);
    }
}
