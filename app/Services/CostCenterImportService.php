<?php

namespace App\Services;

use App\Models\CostCenter;
use App\Models\Manager;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa centros de custo via XLSX/CSV em dois passos (preview → import).
 *
 * Chave de upsert: `code`. Re-importar a mesma planilha é idempotente —
 * registros existentes com mesmo code recebem UPDATE, novos recebem INSERT.
 *
 * Resolução de relacionamentos:
 *  - `parent_code` → `parent_id` (busca em `cost_centers.code`). Ausente ou
 *    não encontrado = NULL silencioso (linha não falha). Valida ciclo.
 *  - `manager_name` → `manager_id` (busca exata em `managers.name`).
 *    Ausente/não encontrado = NULL silencioso.
 */
class CostCenterImportService
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
        'observacao' => 'description',

        // Parent
        'codigo_pai' => 'parent_code',
        'cod_pai' => 'parent_code',
        'pai' => 'parent_code',
        'parent_code' => 'parent_code',
        'parent' => 'parent_code',

        // Manager
        'responsavel' => 'manager_name',
        'gestor' => 'manager_name',
        'gerente' => 'manager_name',
        'manager' => 'manager_name',
        'manager_name' => 'manager_name',

        // Área
        'area' => 'area_id',
        'area_id' => 'area_id',
        'id_area' => 'area_id',

        // Ativo
        'ativo' => 'is_active',
        'is_active' => 'is_active',
        'status' => 'is_active',
    ];

    /**
     * Lê a planilha e devolve preview + erros sem persistir.
     *
     * @return array{
     *   rows: array<int, array>,
     *   errors: array<int, array{row:int, messages:array<string>}>,
     *   valid_count: int,
     *   invalid_count: int,
     * }
     */
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
            $rowNumber = $idx + 2; // +1 header, +1 base-1
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

    /**
     * Persiste os registros válidos. Upsert por `code`.
     *
     * @return array{
     *   created: int,
     *   updated: int,
     *   skipped: int,
     *   errors: array<int, array{row:int, messages:array<string>}>,
     * }
     */
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

                $existing = CostCenter::query()
                    ->where('code', $data['code'])
                    ->whereNull('deleted_at')
                    ->first();

                // Resolve FKs agora (pode depender de registros criados nesta mesma transação)
                $parentId = $this->resolveParentId($data['parent_code'] ?? null, $existing?->id);
                $managerId = $this->resolveManagerId($data['manager_name'] ?? null);

                if ($existing) {
                    $existing->fill([
                        'name' => $data['name'],
                        'description' => $data['description'] ?? $existing->description,
                        'parent_id' => $parentId,
                        'manager_id' => $managerId ?? $existing->manager_id,
                        'area_id' => $data['area_id'] ?? $existing->area_id,
                        'is_active' => $data['is_active'] ?? $existing->is_active,
                        'updated_by_user_id' => $actor->id,
                    ]);
                    $existing->save();
                    $updated++;
                } else {
                    CostCenter::create([
                        'code' => $data['code'],
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'parent_id' => $parentId,
                        'manager_id' => $managerId,
                        'area_id' => $data['area_id'] ?? null,
                        'is_active' => $data['is_active'] ?? true,
                        'created_by_user_id' => $actor->id,
                        'updated_by_user_id' => $actor->id,
                    ]);
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

    // ------------------------------------------------------------------
    // Internals
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
        $key = str_replace(['ç', ' ', '-', '/'], ['c', '_', '_', '_'], $key);
        $key = preg_replace('/[^a-z0-9_]/', '', $key);

        return (string) $key;
    }

    /**
     * @param  array<string,true>  $seenCodes  Códigos já vistos nesta planilha.
     * @return array{0: array<string,mixed>, 1: array<int,string>}
     */
    protected function validateRow(array $row, array $seenCodes): array
    {
        $errors = [];

        $code = $this->str($row['code'] ?? null);
        $name = $this->str($row['name'] ?? null);

        if (! $code) {
            $errors[] = 'Código obrigatório';
        } elseif (strlen($code) > 20) {
            $errors[] = 'Código deve ter no máximo 20 caracteres';
        } elseif (isset($seenCodes[$code])) {
            $errors[] = "Código '{$code}' duplicado na planilha";
        }

        if (! $name) {
            $errors[] = 'Nome obrigatório';
        }

        $data = [
            'code' => $code,
            'name' => $name,
            'description' => $this->str($row['description'] ?? null),
            'parent_code' => $this->str($row['parent_code'] ?? null),
            'manager_name' => $this->str($row['manager_name'] ?? null),
            'area_id' => $this->int($row['area_id'] ?? null),
            'is_active' => $this->bool($row['is_active'] ?? null),
        ];

        return [$data, $errors];
    }

    protected function resolveParentId(?string $parentCode, ?int $selfId): ?int
    {
        if (! $parentCode) {
            return null;
        }

        $parent = CostCenter::query()
            ->where('code', $parentCode)
            ->whereNull('deleted_at')
            ->first();

        if (! $parent) {
            return null; // silencioso — linha não é rejeitada por pai ausente
        }

        // Evita ciclo: se selfId é o próprio pai ou ancestral
        if ($selfId !== null) {
            if ($parent->id === $selfId) {
                return null;
            }
            $ancestors = $parent->ancestorsIds();
            $ancestors[] = $parent->id;
            if (in_array($selfId, $ancestors, true)) {
                return null;
            }
        }

        return $parent->id;
    }

    protected function resolveManagerId(?string $managerName): ?int
    {
        if (! $managerName) {
            return null;
        }

        return Manager::query()
            ->where('name', $managerName)
            ->value('id');
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

        if (in_array($normalized, ['sim', 's', '1', 'true', 'ativo', 'yes'], true)) {
            return true;
        }

        if (in_array($normalized, ['nao', 'não', 'n', '0', 'false', 'inativo', 'no'], true)) {
            return false;
        }

        return null;
    }
}
