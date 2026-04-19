<?php

namespace App\Services;

use App\Models\AccountingClass;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa o plano gerencial via XLSX/CSV em dois passos (preview → import).
 *
 * Chave de upsert: `code`.
 *
 * Resolução de relacionamentos (todos opcionais, ausência = NULL silencioso):
 *  - `parent_code`            → parent_id (rejeita linha se parent é folha)
 *  - `accounting_class_code`  → accounting_class_id (rejeita linha se é agrupador)
 *  - `cost_center_code`       → cost_center_id (silencioso se não existe)
 */
class ManagementClassImportService
{
    private const HEADER_MAP = [
        'codigo' => 'code',
        'cod' => 'code',
        'code' => 'code',

        'nome' => 'name',
        'name' => 'name',

        'descricao' => 'description',
        'description' => 'description',
        'obs' => 'description',

        'codigo_pai' => 'parent_code',
        'cod_pai' => 'parent_code',
        'pai' => 'parent_code',
        'parent_code' => 'parent_code',

        'codigo_contabil' => 'accounting_class_code',
        'conta_contabil' => 'accounting_class_code',
        'accounting_class_code' => 'accounting_class_code',
        'accounting_code' => 'accounting_class_code',

        'codigo_centro_custo' => 'cost_center_code',
        'centro_custo' => 'cost_center_code',
        'cost_center_code' => 'cost_center_code',
        'cc_code' => 'cost_center_code',

        'aceita_lancamento' => 'accepts_entries',
        'aceita_lancamentos' => 'accepts_entries',
        'accepts_entries' => 'accepts_entries',
        'folha' => 'accepts_entries',
        'analitica' => 'accepts_entries',

        'ordem' => 'sort_order',
        'sort_order' => 'sort_order',

        'ativo' => 'is_active',
        'is_active' => 'is_active',
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

                $existing = ManagementClass::query()
                    ->where('code', $data['code'])
                    ->whereNull('deleted_at')
                    ->first();

                $parentResolution = $this->resolveParentId($data['parent_code'] ?? null, $existing?->id);
                if ($parentResolution['error']) {
                    $skipped++;
                    if (count($errors) < 50) {
                        $errors[] = ['row' => $rowNumber, 'messages' => [$parentResolution['error']]];
                    }
                    continue;
                }

                $acResolution = $this->resolveAccountingClassId($data['accounting_class_code'] ?? null);
                if ($acResolution['error']) {
                    $skipped++;
                    if (count($errors) < 50) {
                        $errors[] = ['row' => $rowNumber, 'messages' => [$acResolution['error']]];
                    }
                    continue;
                }

                $ccId = $this->resolveCostCenterId($data['cost_center_code'] ?? null);

                $payload = [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'parent_id' => $parentResolution['id'],
                    'accounting_class_id' => $acResolution['id'],
                    'cost_center_id' => $ccId,
                    'accepts_entries' => $data['accepts_entries'] ?? true,
                    'sort_order' => $data['sort_order'] ?? 0,
                    'is_active' => $data['is_active'] ?? true,
                    'updated_by_user_id' => $actor->id,
                ];

                if ($existing) {
                    $existing->fill($payload)->save();
                    $updated++;
                } else {
                    ManagementClass::create(array_merge([
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

        $data = [
            'code' => $code,
            'name' => $name,
            'description' => $this->str($row['description'] ?? null),
            'parent_code' => $this->str($row['parent_code'] ?? null),
            'accounting_class_code' => $this->str($row['accounting_class_code'] ?? null),
            'cost_center_code' => $this->str($row['cost_center_code'] ?? null),
            'accepts_entries' => $this->bool($row['accepts_entries'] ?? null) ?? true,
            'sort_order' => $this->int($row['sort_order'] ?? null) ?? 0,
            'is_active' => $this->bool($row['is_active'] ?? null) ?? true,
        ];

        return [$data, $errors];
    }

    /**
     * @return array{id: ?int, error: ?string}
     */
    protected function resolveParentId(?string $parentCode, ?int $selfId): array
    {
        if (! $parentCode) {
            return ['id' => null, 'error' => null];
        }

        $parent = ManagementClass::query()
            ->where('code', $parentCode)
            ->whereNull('deleted_at')
            ->first();

        if (! $parent) {
            return ['id' => null, 'error' => null];
        }

        if ($parent->accepts_entries) {
            return [
                'id' => null,
                'error' => "Pai gerencial '{$parent->code}' é folha analítica — agrupadores devem ser sintéticos",
            ];
        }

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

    /**
     * @return array{id: ?int, error: ?string}
     */
    protected function resolveAccountingClassId(?string $code): array
    {
        if (! $code) {
            return ['id' => null, 'error' => null];
        }

        $ac = AccountingClass::query()
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->first();

        if (! $ac) {
            return ['id' => null, 'error' => null]; // silencioso
        }

        if (! $ac->accepts_entries) {
            return [
                'id' => null,
                'error' => "Conta contábil '{$ac->code}' é agrupador sintético — vincule a uma folha analítica",
            ];
        }

        return ['id' => $ac->id, 'error' => null];
    }

    protected function resolveCostCenterId(?string $code): ?int
    {
        if (! $code) {
            return null;
        }

        return CostCenter::query()
            ->where('code', $code)
            ->whereNull('deleted_at')
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

        if (in_array($normalized, ['sim', 's', '1', 'true', 'ativo', 'yes', 'folha', 'analitica'], true)) {
            return true;
        }

        if (in_array($normalized, ['nao', 'não', 'n', '0', 'false', 'inativo', 'no', 'sintetica', 'sintético'], true)) {
            return false;
        }

        return null;
    }
}
