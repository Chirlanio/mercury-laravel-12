<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * STUB — não executa inserção direta.
 *
 * O plano de contas real (1.129 linhas) é populado por
 * `App\Services\DRE\ChartOfAccountsImporter` a partir do XLSX oficial
 * fornecido pelo contador (`docs/Plano de Contas.xlsx`).
 *
 * O import é idempotente (upsert por `reduced_code`) e será entregue
 * no próximo prompt (#2.x do cronograma em `docs/dre-arquitetura.md`).
 *
 * Para popular em ambiente local:
 *   php artisan dre:import-chart-of-accounts docs/Plano\ de\ Contas.xlsx
 *
 * Este seeder existe apenas para evidenciar no `DatabaseSeeder` que o
 * passo existe e onde virá. Mantém a convenção do projeto de declarar
 * explicitamente as origens de dados de referência.
 */
class ChartOfAccountsImportStubSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info(
            '[stub] ChartOfAccounts: popule via `php artisan dre:import-chart-of-accounts <arquivo.xlsx>`'
        );
    }
}
