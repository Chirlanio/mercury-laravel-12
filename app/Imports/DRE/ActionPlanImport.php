<?php

namespace App\Imports\DRE;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Leitor do `Action Plan v1.xlsx` (primeiro orçamento 2026 do Meia Sola).
 *
 * A planilha tem 9 colunas (cabeçalho na linha 1, dados a partir da 2):
 *   - codigo_loja_gerencial  (ex: 421)  → casa com cost_centers.code + stores Z421
 *   - unidade_de_negocio     (redundante — mesmo valor de codigo_loja_gerencial)
 *   - nome_loja              (descritivo — "Schutz Riomar Recife")
 *   - class_contabil         (ex: "3.1.1.01.00012") → chart_of_accounts.code
 *   - class_gerencial        (ex: "8.1.09.01")       → management_classes.code (informativo)
 *   - descricao_contabil     (descritivo)
 *   - mes                    (1..12)
 *   - ano                    (ex: 2026)
 *   - valor                  (numérico — sempre positivo)
 *
 * Apenas lê. Normalização de FKs e persistência ficam em
 * `App\Services\DRE\ActionPlanImporter`.
 */
class ActionPlanImport implements ToCollection, WithHeadingRow
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
                'cc_code' => $this->str($row['codigo_loja_gerencial'] ?? null),
                'store_hint' => $this->str($row['unidade_de_negocio'] ?? null),
                'account_code' => $this->str($row['class_contabil'] ?? null),
                'management_code' => $this->str($row['class_gerencial'] ?? null),
                'month' => $row['mes'] ?? null,
                'year' => $row['ano'] ?? null,
                'amount' => $row['valor'] ?? null,
                'description' => $this->str($row['descricao_contabil'] ?? null),
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
