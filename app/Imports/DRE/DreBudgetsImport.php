<?php

namespace App\Imports\DRE;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Leitor do XLSX de orçado manual da DRE.
 *
 * Colunas (cabeçalho normalizado pelo WithHeadingRow — slugify):
 *   - entry_date (obrigatório) — dia 1 do mês (convenção de dre_budgets).
 *     YYYY-MM-DD ou YYYY-MM; se vier só ano-mês, é normalizado para dia 1.
 *   - store_code (opcional) — código da loja; nullable quando é budget
 *     consolidado (ex: rede inteira).
 *   - account_code (obrigatório) — conta contábil analítica.
 *   - cost_center_code (opcional).
 *   - amount (obrigatório) — positivo; sinal é aplicado no service.
 *   - notes (opcional).
 *
 * `budget_version` vem do form (não está na planilha).
 */
class DreBudgetsImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public int $totalRead = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $fileRow = $index + 2;
            $this->totalRead++;

            $this->rows[] = [
                'entry_date' => $row['entry_date'] ?? null,
                'store_code' => $this->str($row['store_code'] ?? null) ?: null,
                'account_code' => $this->str($row['account_code'] ?? null),
                'cost_center_code' => $this->str($row['cost_center_code'] ?? null) ?: null,
                'amount' => $row['amount'] ?? null,
                'notes' => $this->str($row['notes'] ?? null) ?: null,
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
