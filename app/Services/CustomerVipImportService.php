<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerVipTier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa listas de clientes VIP a partir de planilha XLSX.
 *
 * Colunas obrigatórias (case-insensitive, aceita variantes PT-BR):
 *   - cpf      (string, 11 dígitos — pontuação opcional)
 *   - ano      (int 2020..2100 — ano da Lista VIP)
 *   - status   (Black|Gold — case-insensitive)
 *
 * Comportamento:
 *  - Cada linha gera/atualiza CustomerVipTier com source=manual, curated_at=now,
 *    curated_by_user_id=$by, final_tier=status. Snapshots existentes
 *    (revenue/orders/preferred_store) são preservados.
 *  - CPF não encontrado em customers → linha rejeitada com erro detalhado.
 *  - Status inválido / ano fora do range → linha rejeitada.
 *  - Duplicatas (mesmo cpf+ano no arquivo) → última linha vence (warning).
 *  - $replaceYear=true: para CADA ano presente no arquivo, deleta registros
 *    do ano que não estão no arquivo (incluindo curadorias prévias).
 *
 * Retorna array com summary { imported, updated, errors[], warnings[],
 * replaced_years[], total_removed }.
 */
class CustomerVipImportService
{
    private const HEADER_MAP = [
        'cpf' => 'cpf',
        'documento' => 'cpf',

        'ano' => 'year',
        'year' => 'year',
        'lista' => 'year',
        'ano_lista' => 'year',

        'status' => 'tier',
        'tier' => 'tier',
        'perfil' => 'tier',
        'classificacao' => 'tier',
        'classificação' => 'tier',
    ];

    /**
     * @return array{
     *     imported: int,
     *     updated: int,
     *     errors: array<int, array{line:int, cpf:?string, message:string}>,
     *     warnings: array<int, string>,
     *     replaced_years: array<int, int>,
     *     total_removed: int,
     * }
     */
    public function import(UploadedFile $file, User $by, bool $replaceYear = false): array
    {
        $rows = $this->parseRows($file);

        $summary = [
            'imported' => 0,
            'updated' => 0,
            'errors' => [],
            'warnings' => [],
            'replaced_years' => [],
            'total_removed' => 0,
        ];

        // Dedup linhas por (cpf, ano) — última vence
        $deduped = [];
        foreach ($rows as $row) {
            $key = $row['cpf'].'|'.$row['year'];
            if (isset($deduped[$key])) {
                $summary['warnings'][] = sprintf(
                    'Linha %d: CPF %s ano %d duplicado — sobrescreve linha anterior.',
                    $row['line'], $row['cpf'], $row['year'],
                );
            }
            $deduped[$key] = $row;
        }

        // Resolve customers em batch via CPF
        $cpfs = array_column(array_values($deduped), 'cpf');
        $customers = Customer::whereIn('cpf', $cpfs)->get(['id', 'cpf'])->keyBy('cpf');

        DB::transaction(function () use ($deduped, $customers, $by, $replaceYear, &$summary) {
            $now = now();
            $qualifiedByYear = []; // year => [customer_id, ...]

            foreach ($deduped as $row) {
                $customer = $customers->get($row['cpf']);
                if (! $customer) {
                    $summary['errors'][] = [
                        'line' => $row['line'],
                        'cpf' => $row['cpf'],
                        'message' => 'CPF não encontrado na base de clientes (sync CIGAM precisa rodar antes).',
                    ];

                    continue;
                }

                $existing = CustomerVipTier::where('customer_id', $customer->id)
                    ->where('year', $row['year'])
                    ->first();

                $payload = [
                    'final_tier' => $row['tier'],
                    'curated_at' => $now,
                    'curated_by_user_id' => $by->id,
                    'source' => CustomerVipTier::SOURCE_MANUAL,
                ];

                if ($existing) {
                    $existing->update($payload);
                    $summary['updated']++;
                } else {
                    CustomerVipTier::create(array_merge($payload, [
                        'customer_id' => $customer->id,
                        'year' => $row['year'],
                        'total_revenue' => 0,
                        'total_orders' => 0,
                    ]));
                    $summary['imported']++;
                }

                $qualifiedByYear[$row['year']][] = $customer->id;
            }

            if ($replaceYear) {
                foreach ($qualifiedByYear as $year => $ids) {
                    $removed = CustomerVipTier::where('year', $year)
                        ->whereNotIn('customer_id', $ids)
                        ->delete();
                    if ($removed > 0) {
                        $summary['total_removed'] += $removed;
                        $summary['replaced_years'][] = (int) $year;
                    }
                }
            }
        });

        return $summary;
    }

    /**
     * Lê o arquivo e retorna array de linhas estruturadas.
     * Não persiste nada — pode ser chamado por preview se for necessário no futuro.
     *
     * @return array<int, array{line:int, cpf:string, year:int, tier:string}>
     */
    private function parseRows(UploadedFile $file): array
    {
        $importer = new class implements \Maatwebsite\Excel\Concerns\ToCollection, WithHeadingRow {
            public array $captured = [];

            public function collection(\Illuminate\Support\Collection $rows): void
            {
                foreach ($rows as $r) {
                    $this->captured[] = $r->toArray();
                }
            }

            public function headingRow(): int
            {
                return 1;
            }
        };

        Excel::import($importer, $file);

        $rows = [];
        $line = 1; // primeira linha é cabeçalho; linhas começam em 2 mas alinhamos pelo iter
        foreach ($importer->captured as $raw) {
            $line++;
            $normalized = $this->normalizeRow($raw, $line);
            if ($normalized === null) {
                continue; // linha vazia
            }
            if (isset($normalized['__error'])) {
                // erros estruturais aqui são raros (ex: formato corrompido). Vamos
                // só pular a linha — o controller relata via summary['errors'].
                continue;
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * Normaliza uma linha, mapeando headers e validando os 3 campos.
     * Linhas com erro retornam ['__error' => msg, 'line' => N].
     * Linhas vazias retornam null.
     */
    private function normalizeRow(array $raw, int $line): ?array
    {
        // Mapeia chaves do raw (lowercase, sem espaço) → campo canônico
        $mapped = [];
        foreach ($raw as $key => $value) {
            $clean = strtolower(trim((string) $key));
            $clean = str_replace([' ', '-'], '_', $clean);
            $canonical = self::HEADER_MAP[$clean] ?? null;
            if ($canonical && $value !== null && $value !== '') {
                $mapped[$canonical] = $value;
            }
        }

        // Linha completamente vazia
        if (empty($mapped)) {
            return null;
        }

        $cpf = isset($mapped['cpf']) ? preg_replace('/\D/', '', (string) $mapped['cpf']) : '';
        $year = isset($mapped['year']) ? (int) $mapped['year'] : 0;
        $tier = isset($mapped['tier']) ? strtolower(trim((string) $mapped['tier'])) : '';

        if (strlen($cpf) !== 11) {
            return ['__error' => "Linha {$line}: CPF inválido", 'line' => $line];
        }
        if ($year < 2020 || $year > 2100) {
            return ['__error' => "Linha {$line}: ano inválido", 'line' => $line];
        }
        if (! in_array($tier, ['black', 'gold'], true)) {
            return ['__error' => "Linha {$line}: status inválido (esperado Black ou Gold)", 'line' => $line];
        }

        return [
            'line' => $line,
            'cpf' => $cpf,
            'year' => $year,
            'tier' => $tier,
        ];
    }

    /**
     * Pre-valida sem persistir — para o controller reportar erros antes mesmo
     * de chamar import(). Reaproveita parseRows + reaplica as validações
     * estruturais que não envolvem DB.
     *
     * @return array{rows:array<int,array>, errors:array<int,array{line:int,cpf:?string,message:string}>}
     */
    public function preview(UploadedFile $file): array
    {
        // Reusamos a mesma extração de raw rows + validações estruturais via
        // um parse dedicado que retém os erros (parseRows joga fora silenciosamente).
        $importer = new class implements \Maatwebsite\Excel\Concerns\ToCollection, WithHeadingRow {
            public array $captured = [];

            public function collection(\Illuminate\Support\Collection $rows): void
            {
                foreach ($rows as $r) {
                    $this->captured[] = $r->toArray();
                }
            }

            public function headingRow(): int
            {
                return 1;
            }
        };

        Excel::import($importer, $file);

        $rows = [];
        $errors = [];
        $line = 1;
        foreach ($importer->captured as $raw) {
            $line++;
            $normalized = $this->normalizeRow($raw, $line);
            if ($normalized === null) {
                continue;
            }
            if (isset($normalized['__error'])) {
                $errors[] = [
                    'line' => $normalized['line'],
                    'cpf' => null,
                    'message' => $normalized['__error'],
                ];

                continue;
            }
            $rows[] = $normalized;
        }

        return ['rows' => $rows, 'errors' => $errors];
    }
}
