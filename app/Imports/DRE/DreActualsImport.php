<?php

namespace App\Imports\DRE;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Leitor do XLSX de realizado manual da DRE.
 *
 * Colunas (cabeçalho normalizado pelo WithHeadingRow — slugify):
 *   - entry_date (obrigatório) — YYYY-MM-DD ou serial Excel.
 *   - store_code (obrigatório) — código da loja.
 *   - account_code (obrigatório) — código da conta contábil analítica.
 *   - cost_center_code (opcional).
 *   - amount (obrigatório) — valor positivo; conversão de sinal é no service
 *     conforme `account_group`.
 *   - document (opcional) — nº do documento (NF, recibo etc.).
 *   - description (opcional) — texto livre.
 *   - external_id (opcional) — identificador estável do sistema externo.
 *     Quando presente, dedup por (source=MANUAL_IMPORT, external_id).
 *
 * Não valida FKs aqui — apenas extrai e acumula. Validação e persistência
 * ficam em `DreActualsImporter`.
 */
class DreActualsImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, mixed>> Linhas brutas com _row (nº no arquivo). */
    public array $rows = [];

    public int $totalRead = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $fileRow = $index + 2;
            $this->totalRead++;

            $this->rows[] = [
                'entry_date' => $row['entry_date'] ?? null,
                'store_code' => $this->str($row['store_code'] ?? null),
                'account_code' => $this->str($row['account_code'] ?? null),
                'cost_center_code' => $this->str($row['cost_center_code'] ?? null) ?: null,
                'amount' => $row['amount'] ?? null,
                'document' => $this->str($row['document'] ?? null) ?: null,
                'description' => $this->str($row['description'] ?? null) ?: null,
                'external_id' => $this->str($row['external_id'] ?? null) ?: null,
                '_row' => $fileRow,
            ];
        }
    }

    private function str(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
