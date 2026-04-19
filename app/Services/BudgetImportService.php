<?php

namespace App\Services;

use App\Models\AccountingClass;
use App\Models\BudgetItem;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\Store;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Parser xlsx + preview + fuzzy matching para o módulo Budgets (Fase 2).
 *
 * Fluxo:
 *   1. preview($filePath) — parseia xlsx, normaliza headers, tenta resolver
 *      FKs por code. Retorna diagnóstico estruturado.
 *   2. Cliente vê diagnóstico, mapeia códigos ausentes → IDs existentes
 *      (não cria cadastros novos — decisão da fase de planejamento).
 *   3. import($filePath, $mapping, $actor) — refaz o parse aplicando o
 *      mapping, produz `items[]` final e retorna. O controller então
 *      chama BudgetService::create() com esse items[].
 *
 * Fuzzy matching: Levenshtein. Retorna top-3 candidatos com distância
 * ≤ min(3, 30% do tamanho do código) por entidade.
 */
class BudgetImportService
{
    /**
     * Aliases aceitos para cada coluna do xlsx. Chave normalizada → coluna canônica.
     */
    private const HEADER_MAP = [
        // AccountingClass code
        'codigo_contabil' => 'accounting_code',
        'codigo_conta' => 'accounting_code',
        'conta_contabil' => 'accounting_code',
        'contabil' => 'accounting_code',
        'accounting_code' => 'accounting_code',
        'accounting_class_code' => 'accounting_code',

        // ManagementClass code
        'codigo_gerencial' => 'management_code',
        'codigo_gerencia' => 'management_code',
        'conta_gerencial' => 'management_code',
        'gerencial' => 'management_code',
        'management_code' => 'management_code',
        'management_class_code' => 'management_code',

        // CostCenter code
        'codigo_cc' => 'cost_center_code',
        'codigo_centro_custo' => 'cost_center_code',
        'codigo_centro_de_custo' => 'cost_center_code',
        'centro_custo' => 'cost_center_code',
        'centro_de_custo' => 'cost_center_code',
        'cc' => 'cost_center_code',
        'cost_center_code' => 'cost_center_code',

        // Store code
        'codigo_loja' => 'store_code',
        'loja' => 'store_code',
        'store_code' => 'store_code',

        // Text fields
        'fornecedor' => 'supplier',
        'supplier' => 'supplier',
        'justificativa' => 'justification',
        'justification' => 'justification',
        'descricao_conta' => 'account_description',
        'descricao_contabil' => 'account_description',
        'account_description' => 'account_description',
        'descricao_classe' => 'class_description',
        'descricao_gerencial' => 'class_description',
        'class_description' => 'class_description',

        // Months (PT-BR abbreviated)
        'jan' => 'month_01',
        'fev' => 'month_02',
        'mar' => 'month_03',
        'abr' => 'month_04',
        'mai' => 'month_05',
        'jun' => 'month_06',
        'jul' => 'month_07',
        'ago' => 'month_08',
        'set' => 'month_09',
        'out' => 'month_10',
        'nov' => 'month_11',
        'dez' => 'month_12',

        // Months (PT-BR full)
        'janeiro' => 'month_01',
        'fevereiro' => 'month_02',
        'marco' => 'month_03',
        'abril' => 'month_04',
        'maio' => 'month_05',
        'junho' => 'month_06',
        'julho' => 'month_07',
        'agosto' => 'month_08',
        'setembro' => 'month_09',
        'outubro' => 'month_10',
        'novembro' => 'month_11',
        'dezembro' => 'month_12',

        // Months (numeric)
        '01' => 'month_01',
        '02' => 'month_02',
        '03' => 'month_03',
        '04' => 'month_04',
        '05' => 'month_05',
        '06' => 'month_06',
        '07' => 'month_07',
        '08' => 'month_08',
        '09' => 'month_09',
        '10' => 'month_10',
        '11' => 'month_11',
        '12' => 'month_12',

        // Months (English — bonus)
        'january' => 'month_01',
        'february' => 'month_02',
        'march' => 'month_03',
        'april' => 'month_04',
        'may' => 'month_05',
        'june' => 'month_06',
        'july' => 'month_07',
        'august' => 'month_08',
        'september' => 'month_09',
        'october' => 'month_10',
        'november' => 'month_11',
        'december' => 'month_12',
    ];

    private const FUZZY_MAX_ABS = 3;       // distância máxima absoluta
    private const FUZZY_MAX_PCT = 0.30;    // 30% do tamanho do code
    private const FUZZY_TOP_N = 3;         // top-3 candidatos por ausente
    private const MAX_SUGGESTIONS_UNIVERSE = 500; // limite de cadastros para fuzzy scan

    /**
     * Gera preview estruturado do xlsx sem persistir nada.
     *
     * @return array{
     *   total_rows: int,
     *   valid_rows: int,
     *   needs_reconciliation: int,
     *   rejected_rows: int,
     *   rows: array,
     *   unresolved_summary: array,
     *   totals: array{grand_total: float, by_month: array},
     * }
     */
    public function preview(string $filePath): array
    {
        $raw = $this->readFile($filePath);
        $normalized = $this->normalizeRows($raw);

        // Indexa cadastros ativos por code para lookup O(1)
        $indexes = $this->buildIndexes();

        $rows = [];
        $unresolvedBucket = [
            'accounting_class' => [],
            'management_class' => [],
            'cost_center' => [],
            'store' => [],
        ];

        $validCount = 0;
        $pendingCount = 0;
        $rejectedCount = 0;
        $grandTotal = 0.0;
        $byMonth = array_fill(1, 12, 0.0);

        foreach ($normalized as $idx => $row) {
            $rowNumber = $idx + 2; // +1 heading, +1 base-1
            $result = $this->analyzeRow($row, $indexes, $rowNumber);
            $rows[] = $result;

            if ($result['status'] === 'valid') {
                $validCount++;
                $grandTotal += $result['year_total'];
                foreach ($result['months'] as $m => $v) {
                    $byMonth[$m] += $v;
                }
            } elseif ($result['status'] === 'needs_reconciliation') {
                $pendingCount++;
                foreach ($result['unresolved'] as $type => $code) {
                    if (! isset($unresolvedBucket[$type][$code])) {
                        $unresolvedBucket[$type][$code] = [
                            'code' => $code,
                            'row_numbers' => [],
                            'suggestions' => [],
                        ];
                    }
                    $unresolvedBucket[$type][$code]['row_numbers'][] = $rowNumber;
                }
            } else {
                $rejectedCount++;
            }
        }

        // Aplica fuzzy matching aos códigos ausentes (1 scan por tipo)
        $unresolvedSummary = $this->resolveFuzzySuggestions($unresolvedBucket);

        return [
            'total_rows' => count($normalized),
            'valid_rows' => $validCount,
            'needs_reconciliation' => $pendingCount,
            'rejected_rows' => $rejectedCount,
            'rows' => $rows,
            'unresolved_summary' => $unresolvedSummary,
            'totals' => [
                'grand_total' => round($grandTotal, 2),
                'by_month' => array_map(fn ($v) => round($v, 2), $byMonth),
            ],
        ];
    }

    /**
     * Produz `items[]` final aplicando o mapping de reconciliação do usuário.
     * Linhas com FKs não resolvidas (nem no mapping, nem no cadastro) são
     * rejeitadas silenciosamente.
     *
     * @param  array<string, array<string, int>>  $mapping
     *         Estrutura: ['accounting_class' => ['CODE-XYZ' => 42, ...],
     *                     'management_class' => [...], 'cost_center' => [...],
     *                     'store' => [...]]
     * @return array{items: array, stats: array}
     */
    public function resolveItems(string $filePath, array $mapping = []): array
    {
        $raw = $this->readFile($filePath);
        $normalized = $this->normalizeRows($raw);

        $indexes = $this->buildIndexes();

        $items = [];
        $skipped = 0;
        $validInRows = 0;

        foreach ($normalized as $idx => $row) {
            $rowNumber = $idx + 2;
            $result = $this->analyzeRow($row, $indexes, $rowNumber, $mapping);

            if ($result['status'] === 'valid') {
                $items[] = $result['resolved'];
                $validInRows++;
            } else {
                $skipped++;
            }
        }

        return [
            'items' => $items,
            'stats' => [
                'total_rows' => count($normalized),
                'imported' => $validInRows,
                'skipped' => $skipped,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Row analysis (shared por preview + resolveItems)
    // ------------------------------------------------------------------

    /**
     * Analisa uma linha. Se $mapping for passado, usa-o para preencher
     * FKs que não foram encontradas no cadastro direto.
     *
     * @param  array  $row  Linha normalizada (chaves = canonical).
     * @param  array  $indexes  [accounting=>[code=>id], management=>[...], ...]
     * @param  int  $rowNumber  2-based (inclui header).
     * @param  array  $mapping  (optional) ['accounting_class' => [code=>id], ...]
     * @return array{
     *   row_number: int, status: string, errors: array,
     *   months: array<int,float>, year_total: float,
     *   resolved?: array, unresolved?: array,
     * }
     */
    protected function analyzeRow(array $row, array $indexes, int $rowNumber, array $mapping = []): array
    {
        $errors = [];
        $unresolved = [];

        // ---- Extrai valores mensais ----
        $months = [];
        $yearTotal = 0.0;
        for ($i = 1; $i <= 12; $i++) {
            $col = 'month_'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $v = $this->parseMoney($row[$col] ?? null);
            $months[$i] = $v;
            $yearTotal += $v;
        }

        // ---- Pula linhas completamente vazias (sem erro) ----
        $hasAnyIdentifier = ! empty($row['accounting_code'])
            || ! empty($row['management_code'])
            || ! empty($row['cost_center_code']);
        if ($yearTotal == 0.0 && ! $hasAnyIdentifier) {
            return [
                'row_number' => $rowNumber,
                'status' => 'rejected',
                'errors' => ['Linha vazia — pulada'],
                'months' => $months,
                'year_total' => 0.0,
            ];
        }

        // ---- Valida presença dos códigos obrigatórios ----
        $acCode = $this->str($row['accounting_code'] ?? null);
        $mcCode = $this->str($row['management_code'] ?? null);
        $ccCode = $this->str($row['cost_center_code'] ?? null);
        $storeCode = $this->str($row['store_code'] ?? null);

        if (! $acCode) {
            $errors[] = 'Código contábil obrigatório';
        }
        if (! $mcCode) {
            $errors[] = 'Código gerencial obrigatório';
        }
        if (! $ccCode) {
            $errors[] = 'Código de centro de custo obrigatório';
        }

        if ($yearTotal == 0.0) {
            $errors[] = 'Soma dos 12 meses é zero — linha sem valor';
        } elseif ($yearTotal < 0) {
            $errors[] = 'Total anual negativo não é permitido';
        }

        if (! empty($errors)) {
            return [
                'row_number' => $rowNumber,
                'status' => 'rejected',
                'errors' => $errors,
                'months' => $months,
                'year_total' => $yearTotal,
            ];
        }

        // ---- Resolve FKs (direto no cadastro ou via mapping) ----
        $acId = $indexes['accounting_class'][$acCode] ?? $mapping['accounting_class'][$acCode] ?? null;
        $mcId = $indexes['management_class'][$mcCode] ?? $mapping['management_class'][$mcCode] ?? null;
        $ccId = $indexes['cost_center'][$ccCode] ?? $mapping['cost_center'][$ccCode] ?? null;

        $storeId = null;
        if ($storeCode) {
            $storeId = $indexes['store'][$storeCode] ?? $mapping['store'][$storeCode] ?? null;
            if (! $storeId) {
                $unresolved['store'] = $storeCode;
            }
        }

        if (! $acId) {
            $unresolved['accounting_class'] = $acCode;
        }
        if (! $mcId) {
            $unresolved['management_class'] = $mcCode;
        }
        if (! $ccId) {
            $unresolved['cost_center'] = $ccCode;
        }

        if (! empty($unresolved)) {
            return [
                'row_number' => $rowNumber,
                'status' => 'needs_reconciliation',
                'errors' => [],
                'unresolved' => $unresolved,
                'months' => $months,
                'year_total' => $yearTotal,
                'codes' => [
                    'accounting' => $acCode,
                    'management' => $mcCode,
                    'cost_center' => $ccCode,
                    'store' => $storeCode,
                ],
            ];
        }

        // ---- Linha 100% válida → monta payload para BudgetService ----
        $resolved = [
            'accounting_class_id' => $acId,
            'management_class_id' => $mcId,
            'cost_center_id' => $ccId,
            'store_id' => $storeId,
            'supplier' => $this->str($row['supplier'] ?? null),
            'justification' => $this->str($row['justification'] ?? null),
            'account_description' => $this->str($row['account_description'] ?? null),
            'class_description' => $this->str($row['class_description'] ?? null),
        ];

        for ($i = 1; $i <= 12; $i++) {
            $col = 'month_'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $resolved["{$col}_value"] = $months[$i];
        }

        return [
            'row_number' => $rowNumber,
            'status' => 'valid',
            'errors' => [],
            'months' => $months,
            'year_total' => $yearTotal,
            'resolved' => $resolved,
            'codes' => [
                'accounting' => $acCode,
                'management' => $mcCode,
                'cost_center' => $ccCode,
                'store' => $storeCode,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Indexes + fuzzy
    // ------------------------------------------------------------------

    /**
     * Carrega maps {code=>id} para os 4 tipos de entidade. Indexed em memória
     * uma única vez por preview/import.
     *
     * @return array{accounting_class: array, management_class: array, cost_center: array, store: array}
     */
    protected function buildIndexes(): array
    {
        return [
            'accounting_class' => AccountingClass::query()
                ->whereNull('deleted_at')
                ->where('accepts_entries', true)
                ->pluck('id', 'code')
                ->all(),
            'management_class' => ManagementClass::query()
                ->whereNull('deleted_at')
                ->where('accepts_entries', true)
                ->pluck('id', 'code')
                ->all(),
            'cost_center' => CostCenter::query()
                ->whereNull('deleted_at')
                ->pluck('id', 'code')
                ->all(),
            'store' => Store::query()
                ->pluck('id', 'code')
                ->all(),
        ];
    }

    /**
     * Para cada code ausente, busca top-3 códigos similares via Levenshtein
     * dentro do universo ativo. Aplica threshold min(3, 30% * len).
     *
     * @param  array  $bucket  ['accounting_class' => [code => [row_numbers]], ...]
     * @return array
     */
    protected function resolveFuzzySuggestions(array $bucket): array
    {
        $universes = [
            'accounting_class' => AccountingClass::query()
                ->whereNull('deleted_at')
                ->where('accepts_entries', true)
                ->orderBy('code')
                ->limit(self::MAX_SUGGESTIONS_UNIVERSE)
                ->get(['id', 'code', 'name']),
            'management_class' => ManagementClass::query()
                ->whereNull('deleted_at')
                ->where('accepts_entries', true)
                ->orderBy('code')
                ->limit(self::MAX_SUGGESTIONS_UNIVERSE)
                ->get(['id', 'code', 'name']),
            'cost_center' => CostCenter::query()
                ->whereNull('deleted_at')
                ->orderBy('code')
                ->limit(self::MAX_SUGGESTIONS_UNIVERSE)
                ->get(['id', 'code', 'name']),
            'store' => Store::query()
                ->orderBy('code')
                ->limit(self::MAX_SUGGESTIONS_UNIVERSE)
                ->get(['id', 'code', 'name']),
        ];

        $result = [];
        foreach ($bucket as $type => $codes) {
            $universe = $universes[$type];
            $entries = [];
            foreach ($codes as $code => $info) {
                $entries[] = [
                    'code' => $code,
                    'row_numbers' => array_values(array_unique($info['row_numbers'])),
                    'row_count' => count(array_unique($info['row_numbers'])),
                    'suggestions' => $this->findSimilarCodes($code, $universe),
                ];
            }
            // Ordena por frequência (mais aparições primeiro)
            usort($entries, fn ($a, $b) => $b['row_count'] <=> $a['row_count']);
            $result[$type] = $entries;
        }

        return $result;
    }

    /**
     * Retorna top-N códigos mais próximos do $target dentro do $universe.
     *
     * @param  Collection<\Illuminate\Database\Eloquent\Model>  $universe
     * @return array<int, array{id:int, code:string, name:string, distance:int}>
     */
    protected function findSimilarCodes(string $target, $universe): array
    {
        $targetLen = max(1, strlen($target));
        $pctThreshold = (int) ceil($targetLen * self::FUZZY_MAX_PCT);
        $threshold = max(1, min(self::FUZZY_MAX_ABS, $pctThreshold));

        $candidates = [];
        foreach ($universe as $entry) {
            $distance = levenshtein($target, (string) $entry->code);
            if ($distance <= $threshold) {
                $candidates[] = [
                    'id' => $entry->id,
                    'code' => $entry->code,
                    'name' => $entry->name,
                    'distance' => $distance,
                ];
            }
        }

        usort($candidates, function ($a, $b) {
            return $a['distance'] <=> $b['distance']
                ?: strnatcasecmp($a['code'], $b['code']);
        });

        return array_slice($candidates, 0, self::FUZZY_TOP_N);
    }

    // ------------------------------------------------------------------
    // File parsing + normalization
    // ------------------------------------------------------------------

    protected function readFile(string $filePath): array
    {
        $reader = new class implements ToArray, WithHeadingRow
        {
            public array $rows = [];

            public function array(array $array): void
            {
                $this->rows = $array;
            }
        };

        Excel::import($reader, $filePath);

        return $reader->rows;
    }

    protected function normalizeRows(array $raw): array
    {
        return array_map(function ($row) {
            $out = [];
            foreach ($row as $key => $value) {
                $norm = $this->normalizeKey((string) $key);
                $canonical = self::HEADER_MAP[$norm] ?? $norm;
                $out[$canonical] = is_string($value) ? trim($value) : $value;
            }

            return $out;
        }, $raw);
    }

    protected function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[áàâã]/u', 'a', $key);
        $key = preg_replace('/[éèê]/u', 'e', $key);
        $key = preg_replace('/[íì]/u', 'i', $key);
        $key = preg_replace('/[óòôõ]/u', 'o', $key);
        $key = preg_replace('/[úù]/u', 'u', $key);
        $key = str_replace(['ç', ' ', '-', '/', '.', '$'], ['c', '_', '_', '_', '_', ''], $key);
        $key = preg_replace('/[^a-z0-9_]/', '', $key);

        return (string) $key;
    }

    /**
     * Parse BR-formatted money string ("1.234,56", "1234.56", "1,234.56") → float.
     * Retorna 0.0 se for null, vazio ou inválido.
     */
    protected function parseMoney($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $s = trim((string) $value);
        $s = preg_replace('/[^\d,.\-]/', '', $s);

        if ($s === '' || $s === '-') {
            return 0.0;
        }

        // BR "1.234,56" → 1234.56 (remove pontos como milhares, vírgula como decimal)
        if (preg_match('/^-?\d{1,3}(\.\d{3})+,\d{1,2}$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);

            return round((float) $s, 2);
        }

        // US "1,234.56" → 1234.56 (remove vírgulas como milhares)
        if (preg_match('/^-?\d{1,3}(,\d{3})+\.\d{1,2}$/', $s)) {
            $s = str_replace(',', '', $s);

            return round((float) $s, 2);
        }

        // "1234,56" — só vírgula decimal
        if (preg_match('/^-?\d+,\d{1,2}$/', $s)) {
            $s = str_replace(',', '.', $s);

            return round((float) $s, 2);
        }

        // Fallback
        return round((float) str_replace(',', '.', $s), 2);
    }

    protected function str($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
