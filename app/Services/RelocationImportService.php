<?php

namespace App\Services;

use App\Enums\RelocationPriority;
use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\RelocationItem;
use App\Models\RelocationStatusHistory;
use App\Models\RelocationType;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa remanejos a partir de planilha XLSX/CSV (1 linha = 1 item).
 *
 * Linhas com a MESMA combinação (loja_origem + loja_destino + título?)
 * são agrupadas no mesmo cabeçalho de remanejo. Cabeçalhos diferentes
 * geram remanejos novos.
 *
 * Útil pra migração de planilhas históricas v1 (CSV `;`) e cargas em
 * lote do time de planejamento. Remanejos importados nascem em estado
 * `draft` — usuário precisa solicitar/aprovar pelo fluxo normal.
 *
 * Fluxo em 2 passos (igual ReversalImportService):
 *   1. preview(): valida e devolve sample + erros sem persistir
 *   2. import(): re-valida e grava o que passou
 */
class RelocationImportService
{
    /**
     * Mapeamento de headers PT-BR → campos canônicos.
     *
     * Suporta DOIS modos de entrada de produto, detectados linha a linha:
     *  - Modo Referência+Tamanho (v1): colunas `referencia` + `tamanho`.
     *    Resolução via JOIN products+product_sizes (size pelo `name`).
     *  - Modo Barcode/EAN (novo): coluna `codigo_barras`/`ean`/`barcode`.
     *    Resolução via product_variants (barcode OU aux_reference).
     *
     * Se a linha tem barcode, ele tem prioridade sobre referência+tamanho.
     * Linhas que não casam com o catálogo são persistidas mesmo assim
     * (sem product_id) — útil pra produtos novos não-sincronizados.
     */
    private const HEADER_MAP = [
        // Lojas
        'loja_origem' => 'origin_code',
        'origem' => 'origin_code',
        'cod_origem' => 'origin_code',
        'codigo_origem' => 'origin_code',
        'origin_code' => 'origin_code',

        'loja_destino' => 'destination_code',
        'destino' => 'destination_code',
        'cod_destino' => 'destination_code',
        'codigo_destino' => 'destination_code',
        'destination_code' => 'destination_code',

        // Identificação do cabeçalho
        'titulo' => 'title',
        'descricao' => 'title',
        'descricao_remanejo' => 'title',
        'title' => 'title',

        'tipo' => 'type_code',
        'tipo_remanejo' => 'type_code',
        'type_code' => 'type_code',

        'prioridade' => 'priority',
        'priority' => 'priority',

        'prazo' => 'deadline_days',
        'prazo_dias' => 'deadline_days',
        'deadline_days' => 'deadline_days',

        // Item — Modo A (referência + tamanho)
        'referencia' => 'product_reference',
        'ref' => 'product_reference',
        'sku' => 'product_reference',
        'product_reference' => 'product_reference',

        'produto' => 'product_name',
        'descricao_produto' => 'product_name',
        'product_name' => 'product_name',

        'cor' => 'product_color',
        'product_color' => 'product_color',

        'tamanho' => 'size',
        'size' => 'size',

        // Item — Modo B (barcode / EAN13)
        'codigo_barras' => 'barcode',
        'cod_barras' => 'barcode',
        'codbarras' => 'barcode',
        'ean' => 'barcode',
        'ean13' => 'barcode',
        'barcode' => 'barcode',
        'aux_reference' => 'barcode', // aux_reference também resolve via product_variants

        'quantidade' => 'qty_requested',
        'qtd' => 'qty_requested',
        'qtde' => 'qty_requested',
        'qty' => 'qty_requested',
        'qty_requested' => 'qty_requested',

        'observacao' => 'observations',
        'observacoes' => 'observations',
        'obs' => 'observations',
        'observations' => 'observations',
    ];

    private const SAMPLE_LIMIT = 10;

    /**
     * Lê e valida sem persistir. Retorna sample + erros + métricas de
     * resolução do catálogo.
     *
     * @return array{
     *   sample: array<int, mixed>,
     *   errors: array<int, string>,
     *   total_rows: int,
     *   valid_rows: int,
     *   invalid_rows: int,
     *   groups_count: int,
     *   resolved_by_barcode: int,
     *   resolved_by_reference: int,
     *   unresolved: int,
     * }
     */
    public function preview(string $filePath): array
    {
        $rows = $this->readRows($filePath);

        $stores = Store::pluck('id', 'code')->toArray();
        $types = RelocationType::pluck('id', 'code')->toArray();

        $errors = [];
        $valid = [];
        foreach ($rows as $i => $raw) {
            $line = $i + 2; // +1 header, +1 humano
            $r = $this->normalizeRow($raw);
            $err = $this->validateRow($r, $stores, $types);
            if (! empty($err)) {
                $errors[] = "Linha {$line}: ".implode('; ', $err);
                continue;
            }
            $valid[] = $r;
        }

        // Resolve catálogo em batch — enriquece cada linha com product_id,
        // size_cigam_code, barcode real, product_name e contabiliza método
        $valid = $this->resolveCatalog($valid);

        $stats = $this->countResolution($valid);
        $groups = $this->groupByHeader($valid);

        return [
            'sample' => array_slice($valid, 0, self::SAMPLE_LIMIT),
            'errors' => array_slice($errors, 0, 100),
            'total_rows' => count($rows),
            'valid_rows' => count($valid),
            'invalid_rows' => count($errors),
            'groups_count' => count($groups),
            'resolved_by_barcode' => $stats['resolved_by_barcode'],
            'resolved_by_reference' => $stats['resolved_by_reference'],
            'unresolved' => $stats['unresolved'],
        ];
    }

    /**
     * Persiste os remanejos e itens válidos.
     *
     * @return array{
     *   created: int,
     *   items_created: int,
     *   skipped: int,
     *   errors: array<int, string>,
     * }
     */
    public function import(string $filePath, User $actor): array
    {
        $rows = $this->readRows($filePath);

        $stores = Store::pluck('id', 'code')->toArray();
        $types = RelocationType::pluck('id', 'code')->toArray();
        $defaultTypeId = $types['PLANEJAMENTO'] ?? array_values($types)[0] ?? null;

        $errors = [];
        $valid = [];
        foreach ($rows as $i => $raw) {
            $line = $i + 2;
            $r = $this->normalizeRow($raw);
            $err = $this->validateRow($r, $stores, $types);
            if (! empty($err)) {
                $errors[] = "Linha {$line}: ".implode('; ', $err);
                continue;
            }
            $valid[] = $r;
        }

        // Resolve catálogo em batch antes de agrupar — enriquece cada linha
        // com product_id, barcode real, size_cigam_code e product_name.
        $valid = $this->resolveCatalog($valid);
        $groups = $this->groupByHeader($valid);

        $created = 0;
        $itemsCreated = 0;
        $skipped = 0;

        DB::transaction(function () use ($groups, $stores, $types, $defaultTypeId, $actor, &$created, &$itemsCreated) {
            foreach ($groups as $key => $group) {
                $head = $group['header'];
                $items = $group['items'];

                $relocation = Relocation::create([
                    'ulid' => (string) Str::ulid(),
                    'relocation_type_id' => $head['type_id'] ?? $defaultTypeId,
                    'origin_store_id' => $head['origin_id'],
                    'destination_store_id' => $head['destination_id'],
                    'title' => $head['title'] ?? null,
                    'priority' => $head['priority'] ?? RelocationPriority::NORMAL->value,
                    'deadline_days' => $head['deadline_days'] ?? null,
                    'status' => RelocationStatus::DRAFT->value,
                    'created_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                ]);

                foreach ($items as $it) {
                    RelocationItem::create([
                        'relocation_id' => $relocation->id,
                        'product_id' => $it['product_id'] ?? null,
                        'product_reference' => $it['product_reference'] ?? ($it['barcode'] ?? '—'),
                        'product_name' => $it['product_name'] ?? null,
                        'product_color' => $it['product_color'] ?? null,
                        'size' => $it['size'] ?? null,
                        'barcode' => $it['barcode'] ?? null,
                        'qty_requested' => (int) $it['qty_requested'],
                        'qty_separated' => 0,
                        'qty_received' => 0,
                        'dispatched_quantity' => 0,
                        'received_quantity' => 0,
                        'observations' => $it['observations'] ?? null,
                    ]);
                    $itemsCreated++;
                }

                RelocationStatusHistory::create([
                    'relocation_id' => $relocation->id,
                    'from_status' => null,
                    'to_status' => RelocationStatus::DRAFT->value,
                    'changed_by_user_id' => $actor->id,
                    'note' => 'Importado via planilha',
                    'created_at' => now(),
                ]);

                $created++;
            }
        });

        return [
            'created' => $created,
            'items_created' => $itemsCreated,
            'skipped' => count($errors),
            'errors' => array_slice($errors, 0, 100),
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function readRows(string $filePath): array
    {
        $reader = new class implements ToArray, WithHeadingRow {
            public array $rows = [];

            public function array(array $array): void
            {
                $this->rows = $array;
            }

            public function headingRow(): int
            {
                return 1;
            }
        };

        Excel::import($reader, $filePath);

        return $reader->rows;
    }

    /**
     * Normaliza chaves usando HEADER_MAP. Suporta valores em qualquer
     * caixa (lower/upper) e com/sem acento.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    protected function normalizeRow(array $raw): array
    {
        $result = [];
        foreach ($raw as $key => $value) {
            $normalized = $this->normalizeHeader((string) $key);
            $canonical = self::HEADER_MAP[$normalized] ?? null;
            if ($canonical) {
                $result[$canonical] = is_string($value) ? trim($value) : $value;
            }
        }
        return $result;
    }

    protected function normalizeHeader(string $header): string
    {
        $h = mb_strtolower(trim($header));
        // Remove acentos comuns
        $h = strtr($h, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ç' => 'c',
        ]);
        // Espaços e hífens -> underscore
        $h = preg_replace('/[\s\-\/]+/', '_', $h);
        return $h;
    }

    /**
     * @param array<string, mixed> $r
     * @param array<string, int> $stores
     * @param array<string, int> $types
     * @return array<int, string>
     */
    protected function validateRow(array $r, array $stores, array $types): array
    {
        $errors = [];

        $origin = strtoupper((string) ($r['origin_code'] ?? ''));
        $dest = strtoupper((string) ($r['destination_code'] ?? ''));

        if (! $origin) {
            $errors[] = 'loja_origem ausente';
        } elseif (! isset($stores[$origin])) {
            $errors[] = "loja_origem '{$origin}' não cadastrada";
        }

        if (! $dest) {
            $errors[] = 'loja_destino ausente';
        } elseif (! isset($stores[$dest])) {
            $errors[] = "loja_destino '{$dest}' não cadastrada";
        }

        if ($origin && $dest && $origin === $dest) {
            $errors[] = 'loja_origem e loja_destino devem ser diferentes';
        }

        // Aceita Modo A (referencia) OU Modo B (barcode/EAN) — pelo menos um
        // dos dois precisa estar preenchido. Resolução do catálogo é
        // tentada depois em resolveCatalog (best-effort).
        $ref = trim((string) ($r['product_reference'] ?? ''));
        $bc = trim((string) ($r['barcode'] ?? ''));
        if ($ref === '' && $bc === '') {
            $errors[] = 'informe referencia + tamanho OU codigo_barras';
        }

        $qty = (int) ($r['qty_requested'] ?? 0);
        if ($qty <= 0) {
            $errors[] = 'qty_requested deve ser > 0';
        }

        if (! empty($r['type_code'])) {
            $tc = strtoupper((string) $r['type_code']);
            if (! isset($types[$tc])) {
                $errors[] = "tipo '{$tc}' não cadastrado";
            }
        }

        if (! empty($r['priority'])) {
            $p = strtolower((string) $r['priority']);
            $validPriorities = array_column(RelocationPriority::cases(), 'value');
            // Aceita também labels PT-BR
            $aliases = ['baixa' => 'low', 'normal' => 'normal', 'alta' => 'high', 'urgente' => 'urgent'];
            $p = $aliases[$p] ?? $p;
            if (! in_array($p, $validPriorities, true)) {
                $errors[] = "prioridade '{$r['priority']}' inválida";
            }
        }

        return $errors;
    }

    /**
     * Resolve linhas contra o catálogo (`product_variants` + `products`
     * + `product_sizes`) em batch — 2 queries totais, independente do
     * volume do arquivo. Enriquece cada linha com `product_id`, `barcode`
     * real, `size_cigam_code`, `product_name` e marca `_resolved_by` com
     * `'barcode'`, `'reference'` ou `'unresolved'`.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function resolveCatalog(array $rows): array
    {
        if (empty($rows)) return $rows;

        // Coleta valores pra lookup
        $barcodes = collect($rows)->pluck('barcode')->filter()->map(fn ($b) => trim((string) $b))->unique()->values()->all();
        $refs = collect($rows)->pluck('product_reference')->filter()->map(fn ($r) => trim((string) $r))->unique()->values()->all();

        // Lookup 1 — por barcode OU aux_reference em product_variants
        $byBarcode = [];
        if (! empty($barcodes)) {
            $variants = DB::table('product_variants as pv')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->where(function ($q) use ($barcodes) {
                    $q->whereIn('pv.barcode', $barcodes)
                        ->orWhereIn('pv.aux_reference', $barcodes);
                })
                ->select(
                    'pv.id as variant_id',
                    'pv.product_id',
                    'pv.barcode',
                    'pv.aux_reference',
                    'pv.size_cigam_code',
                    'p.reference',
                    'p.description',
                )
                ->get();

            foreach ($variants as $v) {
                if ($v->barcode) $byBarcode[$v->barcode] = $v;
                if ($v->aux_reference) $byBarcode[$v->aux_reference] = $v;
            }
        }

        // Lookup 2 — por reference + size.name (label comercial v1) em
        // product_variants JOIN products JOIN product_sizes
        $byRefSize = [];
        if (! empty($refs)) {
            $variants = DB::table('product_variants as pv')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->leftJoin('product_sizes as ps', 'ps.cigam_code', '=', 'pv.size_cigam_code')
                ->whereIn('p.reference', $refs)
                ->select(
                    'pv.id as variant_id',
                    'pv.product_id',
                    'pv.barcode',
                    'pv.size_cigam_code',
                    'ps.name as size_name',
                    'p.reference',
                    'p.description',
                )
                ->get();

            foreach ($variants as $v) {
                $sizeKey = trim((string) ($v->size_name ?? ''));
                $byRefSize[$v->reference.'|'.$sizeKey] = $v;
                // Aceita também size_cigam_code direto como fallback
                $byRefSize[$v->reference.'|cc:'.$v->size_cigam_code] = $v;
            }
        }

        // Aplica enriquecimento — barcode tem prioridade
        return array_map(function (array $r) use ($byBarcode, $byRefSize) {
            $bc = trim((string) ($r['barcode'] ?? ''));
            $ref = trim((string) ($r['product_reference'] ?? ''));
            $size = trim((string) ($r['size'] ?? ''));

            if ($bc !== '' && isset($byBarcode[$bc])) {
                $v = $byBarcode[$bc];
                $r['product_id'] = $v->product_id;
                $r['barcode'] = $v->barcode;
                $r['product_reference'] = ($r['product_reference'] ?? '') ?: $v->reference;
                $r['product_name'] = $r['product_name'] ?? $v->description;
                $r['size'] = ($r['size'] ?? '') ?: $v->size_cigam_code;
                $r['_resolved_by'] = 'barcode';
                return $r;
            }

            if ($ref !== '') {
                $key = $ref.'|'.$size;
                $keyByCigam = $ref.'|cc:'.$size;
                $v = $byRefSize[$key] ?? $byRefSize[$keyByCigam] ?? null;
                if ($v) {
                    $r['product_id'] = $v->product_id;
                    $r['barcode'] = ($r['barcode'] ?? '') ?: $v->barcode;
                    $r['product_name'] = $r['product_name'] ?? $v->description;
                    $r['_resolved_by'] = 'reference';
                    return $r;
                }
            }

            $r['_resolved_by'] = 'unresolved';
            return $r;
        }, $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{resolved_by_barcode: int, resolved_by_reference: int, unresolved: int}
     */
    protected function countResolution(array $rows): array
    {
        $stats = ['resolved_by_barcode' => 0, 'resolved_by_reference' => 0, 'unresolved' => 0];
        foreach ($rows as $r) {
            $by = $r['_resolved_by'] ?? 'unresolved';
            if ($by === 'barcode') $stats['resolved_by_barcode']++;
            elseif ($by === 'reference') $stats['resolved_by_reference']++;
            else $stats['unresolved']++;
        }
        return $stats;
    }

    /**
     * Agrupa linhas válidas em remanejos. Chave = origin + destination + title (se houver).
     *
     * @param array<int, array<string, mixed>> $valid
     * @return array<string, array{header: array<string, mixed>, items: array<int, array<string, mixed>>}>
     */
    protected function groupByHeader(array $valid): array
    {
        $stores = Store::pluck('id', 'code')->toArray();
        $types = RelocationType::pluck('id', 'code')->toArray();

        $groups = [];

        foreach ($valid as $r) {
            $origin = strtoupper((string) ($r['origin_code'] ?? ''));
            $dest = strtoupper((string) ($r['destination_code'] ?? ''));
            $title = trim((string) ($r['title'] ?? ''));

            $key = $origin.'|'.$dest.'|'.$title;

            if (! isset($groups[$key])) {
                $typeCode = strtoupper((string) ($r['type_code'] ?? ''));
                $priority = strtolower((string) ($r['priority'] ?? 'normal'));
                $aliases = ['baixa' => 'low', 'normal' => 'normal', 'alta' => 'high', 'urgente' => 'urgent'];
                $priority = $aliases[$priority] ?? $priority;

                $groups[$key] = [
                    'header' => [
                        'origin_id' => $stores[$origin] ?? null,
                        'destination_id' => $stores[$dest] ?? null,
                        'title' => $title !== '' ? $title : null,
                        'type_id' => $types[$typeCode] ?? null,
                        'priority' => $priority,
                        'deadline_days' => isset($r['deadline_days']) ? (int) $r['deadline_days'] : null,
                    ],
                    'items' => [],
                ];
            }

            $groups[$key]['items'][] = $r;
        }

        return $groups;
    }
}
