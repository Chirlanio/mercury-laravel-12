<?php

namespace App\Services;

use App\Enums\DamagedProductStatus;
use App\Enums\FootSide;
use App\Models\DamagedProduct;
use App\Models\DamageType;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa produtos avariados via planilha XLSX/CSV — usado pra migração de
 * dados históricos da v1 (adms_damaged_products).
 *
 * Colunas esperadas (case-insensitive, snake_case ou Title Case):
 *   loja, referencia, descricao (opcional), cor (opcional),
 *   marca (opcional), tamanho (opcional),
 *   par_trocado (S/N), pe_trocado (esquerdo/direito),
 *   tamanho_real, tamanho_esperado,
 *   avariado (S/N), tipo_dano (nome ou slug), pe_avariado (esquerdo/direito/ambos/na),
 *   descricao_dano (opcional),
 *   notas (opcional)
 *
 * Política:
 *  - Linhas inválidas são reportadas mas NÃO interrompem o batch
 *  - Tudo ou nada por linha (DB::transaction)
 *  - Status sempre = 'open' no import (matching corre pós-import)
 *  - Sem fotos no import (só metadados; fotos sobem manualmente depois)
 */
class DamagedProductImportService
{
    /**
     * @return array{imported:int,errors:list<array{row:int,error:string,raw:array}>}
     */
    public function importFromUploadedFile(UploadedFile $file, User $actor): array
    {
        $sheets = Excel::toCollection(new class implements ToCollection, WithHeadingRow {
            public Collection $rows;
            public function __construct() { $this->rows = collect(); }
            public function collection(Collection $collection)
            {
                $this->rows = $collection;
            }
        }, $file);

        $rows = $sheets->first() ?? collect();

        return $this->importRows($rows, $actor);
    }

    /**
     * @param  Collection<int,Collection>  $rows  Cada item é uma linha (Collection key→value)
     * @return array{imported:int,errors:list<array{row:int,error:string,raw:array}>}
     */
    public function importRows(Collection $rows, User $actor): array
    {
        $imported = 0;
        $errors = [];

        // Caches para reduzir queries
        $storesByCode = Store::pluck('id', 'code');
        $damageTypesBySlug = DamageType::pluck('id', 'slug');
        $damageTypesByName = DamageType::pluck('id', 'name');

        foreach ($rows as $idx => $row) {
            $rowNumber = $idx + 2; // header é linha 1
            $raw = $row->toArray();

            try {
                DB::transaction(function () use ($row, $actor, $storesByCode, $damageTypesBySlug, $damageTypesByName, &$imported) {
                    $payload = $this->mapRow($row, $storesByCode, $damageTypesBySlug, $damageTypesByName);

                    DamagedProduct::create($payload + [
                        'status' => DamagedProductStatus::OPEN->value,
                        'created_by_user_id' => $actor->id,
                        'expires_at' => now()->addDays(90),
                    ]);

                    $imported++;
                });
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'error' => $e->getMessage(),
                    'raw' => $raw,
                ];
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    /**
     * @param  Collection  $row     Linha bruta da planilha (heading row aplicado)
     * @return array<string,mixed>
     */
    protected function mapRow(Collection $row, Collection $storesByCode, Collection $damageTypesBySlug, Collection $damageTypesByName): array
    {
        $loja = (string) $this->get($row, 'loja');
        $storeId = $storesByCode->get(strtoupper($loja));
        if (! $storeId) {
            throw new \RuntimeException("Loja '{$loja}' não encontrada.");
        }

        $reference = strtoupper(trim((string) $this->get($row, 'referencia')));
        if ($reference === '') {
            throw new \RuntimeException('Referência é obrigatória.');
        }

        $isMismatched = $this->parseBool($this->get($row, 'par_trocado'));
        $isDamaged = $this->parseBool($this->get($row, 'avariado'));

        if (! $isMismatched && ! $isDamaged) {
            throw new \RuntimeException('Linha precisa marcar par trocado ou avariado.');
        }

        $payload = [
            'store_id' => $storeId,
            'product_reference' => $reference,
            'product_name' => $this->get($row, 'descricao') ?: null,
            'product_color' => $this->get($row, 'cor') ?: null,
            'brand_cigam_code' => $this->get($row, 'marca') ?: null,
            'product_size' => $this->get($row, 'tamanho') ?: null,
            'is_mismatched' => $isMismatched,
            'is_damaged' => $isDamaged,
            'notes' => $this->get($row, 'notas') ?: null,
        ];

        if ($isMismatched) {
            $foot = $this->parseFoot($this->get($row, 'pe_trocado'), single: true);
            $actual = (string) $this->get($row, 'tamanho_real');
            $expected = (string) $this->get($row, 'tamanho_esperado');

            if (! $foot) throw new \RuntimeException('pe_trocado inválido (esquerdo/direito).');
            if ($actual === '') throw new \RuntimeException('tamanho_real obrigatório em par trocado.');
            if ($expected === '') throw new \RuntimeException('tamanho_esperado obrigatório em par trocado.');
            if ($actual === $expected) throw new \RuntimeException('tamanho_real deve diferir de tamanho_esperado.');

            $payload['mismatched_foot'] = $foot;
            $payload['mismatched_actual_size'] = $actual;
            $payload['mismatched_expected_size'] = $expected;
        }

        if ($isDamaged) {
            $tipoRaw = (string) $this->get($row, 'tipo_dano');
            $tipoSlug = strtolower(trim($tipoRaw));
            $tipoId = $damageTypesBySlug->get($tipoSlug) ?? $damageTypesByName->get($tipoRaw);

            if (! $tipoId) throw new \RuntimeException("Tipo de dano '{$tipoRaw}' não cadastrado.");

            $foot = $this->parseFoot($this->get($row, 'pe_avariado'), single: false);
            if (! $foot) throw new \RuntimeException('pe_avariado inválido (esquerdo/direito/ambos/na).');

            $payload['damage_type_id'] = $tipoId;
            $payload['damaged_foot'] = $foot;
            $payload['damage_description'] = $this->get($row, 'descricao_dano') ?: null;
        }

        return $payload;
    }

    /**
     * Lê uma chave da linha tolerando variações de case (snake_case ou
     * com acentos/espaços via slug).
     */
    protected function get(Collection $row, string $key): mixed
    {
        if ($row->has($key)) return $row->get($key);

        // Tenta variações comuns
        $variations = [
            $key,
            strtoupper($key),
            ucfirst(str_replace('_', ' ', $key)),
        ];
        foreach ($variations as $v) {
            if ($row->has($v)) return $row->get($v);
        }

        return null;
    }

    protected function parseBool(mixed $value): bool
    {
        if ($value === null) return false;
        if (is_bool($value)) return $value;
        if (is_int($value)) return $value === 1;
        $s = strtolower(trim((string) $value));
        return in_array($s, ['s', 'sim', 'yes', 'y', 'true', '1', 'x'], true);
    }

    protected function parseFoot(mixed $value, bool $single): ?string
    {
        if ($value === null) return null;
        $s = strtolower(trim((string) $value));

        $map = [
            'esquerdo' => FootSide::LEFT->value,
            'esq' => FootSide::LEFT->value,
            'left' => FootSide::LEFT->value,
            'l' => FootSide::LEFT->value,
            'direito' => FootSide::RIGHT->value,
            'dir' => FootSide::RIGHT->value,
            'right' => FootSide::RIGHT->value,
            'r' => FootSide::RIGHT->value,
        ];

        if (! $single) {
            $map['ambos'] = FootSide::BOTH->value;
            $map['both'] = FootSide::BOTH->value;
            $map['na'] = FootSide::NA->value;
            $map['n/a'] = FootSide::NA->value;
            $map['nao se aplica'] = FootSide::NA->value;
        }

        return $map[$s] ?? null;
    }
}
