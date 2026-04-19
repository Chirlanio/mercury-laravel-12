<?php

namespace App\Services;

use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use App\Models\AccountingClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa o plano de contas via XLSX/CSV em dois passos (preview → import).
 *
 * Chave de upsert: `code`. Re-importar a mesma planilha é idempotente.
 *
 * Resolução de relacionamentos:
 *  - `parent_code` → `parent_id` (busca em accounting_classes.code).
 *    Parent ausente/não encontrado = NULL silencioso, **mas** parent que
 *    seja folha analítica causa erro (agrupadores devem ser sintéticos).
 *
 * Validações rígidas (rejeitam linha):
 *  - code obrigatório, ≤ 30 chars
 *  - name obrigatório
 *  - nature deve ser debit/credit
 *  - dre_group deve ser um dos 11 grupos válidos
 *  - code duplicado dentro da planilha
 */
class AccountingClassImportService
{
    private const HEADER_MAP = [
        // Código
        'codigo' => 'code',
        'cod' => 'code',
        'code' => 'code',

        // Nome
        'nome' => 'name',
        'name' => 'name',
        'descricao_curta' => 'name',

        // Descrição
        'descricao' => 'description',
        'description' => 'description',
        'obs' => 'description',

        // Parent
        'codigo_pai' => 'parent_code',
        'cod_pai' => 'parent_code',
        'pai' => 'parent_code',
        'parent_code' => 'parent_code',
        'parent' => 'parent_code',

        // Natureza
        'natureza' => 'nature',
        'nature' => 'nature',
        'dc' => 'nature',

        // Grupo DRE
        'grupo_dre' => 'dre_group',
        'dre_group' => 'dre_group',
        'grupo' => 'dre_group',
        'dre' => 'dre_group',

        // Accepts entries
        'aceita_lancamento' => 'accepts_entries',
        'aceita_lancamentos' => 'accepts_entries',
        'accepts_entries' => 'accepts_entries',
        'folha' => 'accepts_entries',
        'analitica' => 'accepts_entries',

        // Sort
        'ordem' => 'sort_order',
        'sort_order' => 'sort_order',

        // Active
        'ativo' => 'is_active',
        'is_active' => 'is_active',
    ];

    /**
     * Aliases de labels PT-BR → enum value para `nature`.
     */
    private const NATURE_MAP = [
        'debit' => 'debit',
        'd' => 'debit',
        'devedora' => 'debit',
        'devedor' => 'debit',
        'debito' => 'debit',
        'débito' => 'debit',

        'credit' => 'credit',
        'c' => 'credit',
        'credora' => 'credit',
        'credor' => 'credit',
        'credito' => 'credit',
        'crédito' => 'credit',
    ];

    public function preview(string $filePath, int $limit = 10): array
    {
        $raw = $this->readFile($filePath);
        $normalized = $this->normalizeRows($raw);

        $rows = [];
        $errors = [];
        $validCount = 0;
        $invalidCount = 0;
        $seenCodes = [];

        foreach ($normalized as $idx => $row) {
            $rowNumber = $idx + 2;
            [$data, $rowErrors] = $this->validateRow($row, $seenCodes);

            if (empty($rowErrors)) {
                $validCount++;
                $seenCodes[$data['code']] = true;
                if (count($rows) < $limit) {
                    $rows[] = $data;
                }
            } else {
                $invalidCount++;
                if (count($errors) < 50) {
                    $errors[] = ['row' => $rowNumber, 'messages' => $rowErrors];
                }
            }
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
        ];
    }

    public function import(string $filePath, User $actor): array
    {
        $raw = $this->readFile($filePath);
        $normalized = $this->normalizeRows($raw);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $seenCodes = [];

        DB::transaction(function () use ($normalized, $actor, &$created, &$updated, &$skipped, &$errors, &$seenCodes) {
            foreach ($normalized as $idx => $row) {
                $rowNumber = $idx + 2;
                [$data, $rowErrors] = $this->validateRow($row, $seenCodes);

                if (! empty($rowErrors)) {
                    $skipped++;
                    if (count($errors) < 50) {
                        $errors[] = ['row' => $rowNumber, 'messages' => $rowErrors];
                    }
                    continue;
                }

                $seenCodes[$data['code']] = true;

                $existing = AccountingClass::query()
                    ->where('code', $data['code'])
                    ->whereNull('deleted_at')
                    ->first();

                // Resolve parent (pode depender de outros registros criados agora)
                $parentResolution = $this->resolveParentId($data['parent_code'] ?? null, $existing?->id);
                if ($parentResolution['error']) {
                    $skipped++;
                    if (count($errors) < 50) {
                        $errors[] = ['row' => $rowNumber, 'messages' => [$parentResolution['error']]];
                    }
                    continue;
                }

                $payload = [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'parent_id' => $parentResolution['id'],
                    'nature' => $data['nature'],
                    'dre_group' => $data['dre_group'],
                    'accepts_entries' => $data['accepts_entries'] ?? true,
                    'sort_order' => $data['sort_order'] ?? 0,
                    'is_active' => $data['is_active'] ?? true,
                    'updated_by_user_id' => $actor->id,
                ];

                if ($existing) {
                    $existing->fill($payload)->save();
                    $updated++;
                } else {
                    AccountingClass::create(array_merge([
                        'code' => $data['code'],
                        'created_by_user_id' => $actor->id,
                    ], $payload));
                    $created++;
                }
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

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
        $key = str_replace(['ç', ' ', '-', '/'], ['c', '_', '_', '_'], $key);
        $key = preg_replace('/[^a-z0-9_]/', '', $key);

        return (string) $key;
    }

    protected function validateRow(array $row, array $seenCodes): array
    {
        $errors = [];

        $code = $this->str($row['code'] ?? null);
        $name = $this->str($row['name'] ?? null);

        if (! $code) {
            $errors[] = 'Código obrigatório';
        } elseif (strlen($code) > 30) {
            $errors[] = 'Código deve ter no máximo 30 caracteres';
        } elseif (isset($seenCodes[$code])) {
            $errors[] = "Código '{$code}' duplicado na planilha";
        }

        if (! $name) {
            $errors[] = 'Nome obrigatório';
        }

        // Natureza
        $natureRaw = strtolower(trim((string) ($row['nature'] ?? '')));
        $nature = self::NATURE_MAP[$natureRaw] ?? null;
        if (! $nature) {
            $errors[] = 'Natureza inválida (use "debit"/"credit" ou "devedora"/"credora")';
        }

        // DRE group
        $dreGroupRaw = strtolower(trim((string) ($row['dre_group'] ?? '')));
        $validGroups = array_column(DreGroup::cases(), 'value');
        $dreGroup = in_array($dreGroupRaw, $validGroups, true) ? $dreGroupRaw : null;
        if (! $dreGroup) {
            $errors[] = "Grupo DRE inválido (válidos: ".implode(', ', $validGroups).")";
        }

        $data = [
            'code' => $code,
            'name' => $name,
            'description' => $this->str($row['description'] ?? null),
            'parent_code' => $this->str($row['parent_code'] ?? null),
            'nature' => $nature,
            'dre_group' => $dreGroup,
            'accepts_entries' => $this->bool($row['accepts_entries'] ?? null) ?? true,
            'sort_order' => $this->int($row['sort_order'] ?? null) ?? 0,
            'is_active' => $this->bool($row['is_active'] ?? null) ?? true,
        ];

        return [$data, $errors];
    }

    /**
     * Resolve parent_code → id, retornando erro se o pai for folha analítica.
     *
     * @return array{id: ?int, error: ?string}
     */
    protected function resolveParentId(?string $parentCode, ?int $selfId): array
    {
        if (! $parentCode) {
            return ['id' => null, 'error' => null];
        }

        $parent = AccountingClass::query()
            ->where('code', $parentCode)
            ->whereNull('deleted_at')
            ->first();

        if (! $parent) {
            // Silencioso — pai ausente não invalida a linha (FK fica NULL).
            return ['id' => null, 'error' => null];
        }

        if ($parent->accepts_entries) {
            return [
                'id' => null,
                'error' => "Pai '{$parent->code}' é folha analítica — grupos agrupadores devem ser sintéticos",
            ];
        }

        // Ciclo: selfId no caminho ancestral
        if ($selfId !== null) {
            if ($parent->id === $selfId) {
                return ['id' => null, 'error' => 'Conta não pode ser pai de si mesma'];
            }
            $ancestors = $parent->ancestorsIds();
            $ancestors[] = $parent->id;
            if (in_array($selfId, $ancestors, true)) {
                return ['id' => null, 'error' => 'Vínculo criaria ciclo na hierarquia'];
            }
        }

        return ['id' => $parent->id, 'error' => null];
    }

    protected function str($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    protected function int($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function bool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['sim', 's', '1', 'true', 'ativo', 'yes', 'folha', 'analitica'], true)) {
            return true;
        }

        if (in_array($normalized, ['nao', 'não', 'n', '0', 'false', 'inativo', 'no', 'sintetica', 'sintético'], true)) {
            return false;
        }

        return null;
    }
}
