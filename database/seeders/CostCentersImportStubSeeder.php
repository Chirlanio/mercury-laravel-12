<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * STUB — não executa inserção direta.
 *
 * Os centros de custo departamentais reais (11 linhas `8.1.01..8.1.11`:
 * Marketing, Operações, Planejamento, Gente e Gestão, DP, Financeiro,
 * Fiscal, TI, Comercial, CFO, Diretoria) são populados por
 * `App\Services\DRE\CostCentersImporter` a partir do grupo 8 do XLSX
 * oficial do plano de contas.
 *
 * Os 24 CCs atuais (códigos 421..457) na verdade representam `stores.code`
 * — serão soft-deleted pelo importador, que então semeia os 11 reais.
 *
 * Para popular em ambiente local:
 *   php artisan dre:import-cost-centers docs/Plano\ de\ Contas.xlsx
 *
 * Este seeder existe apenas para evidenciar no `DatabaseSeeder` o passo
 * e sua origem.
 */
class CostCentersImportStubSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info(
            '[stub] CostCenters: popule via `php artisan dre:import-cost-centers <arquivo.xlsx>`'
        );
    }
}
