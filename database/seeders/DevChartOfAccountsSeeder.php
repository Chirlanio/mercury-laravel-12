<?php

namespace Database\Seeders;

use App\Services\DRE\ChartOfAccountsImporter;
use Illuminate\Database\Seeder;

/**
 * Popula plano de contas + centros de custo para ambiente de desenvolvimento
 * a partir da fixture reduzida em `database/seeders/fixtures/plano-contas-exemplo.xlsx`
 * (~30 linhas cobrindo todos os V_Grupo).
 *
 * NÃO é registrado no `DatabaseSeeder` nem no `TenantDatabaseSeeder` de
 * propósito — o plano de contas real é imenso (1.129 linhas) e rodar esse
 * seeder em prod seria antipadrão. Em dev, invocar manualmente:
 *
 *   php artisan db:seed --class=DevChartOfAccountsSeeder
 *
 * Para regenerar a fixture após mudanças no formato, rode:
 *
 *   php database/seeders/fixtures/generate_plano_contas_exemplo.php
 */
class DevChartOfAccountsSeeder extends Seeder
{
    public function run(ChartOfAccountsImporter $importer): void
    {
        $path = database_path('seeders/fixtures/plano-contas-exemplo.xlsx');

        if (! is_file($path)) {
            $this->command?->error(
                "Fixture ausente em {$path}. Rode: php database/seeders/fixtures/generate_plano_contas_exemplo.php"
            );

            return;
        }

        $this->command?->info("Importando fixture de dev: {$path}");

        $report = $importer->import($path, source: 'CIGAM');

        $this->command?->line(sprintf(
            '  Contas: %d novas / %d atualizadas (parents: %d).',
            $report->accountsCreated,
            $report->accountsUpdated,
            $report->accountsLinkedToParent
        ));
        $this->command?->line(sprintf(
            '  Centros de custo: %d novos / %d atualizados (parents: %d).',
            $report->costCentersCreated,
            $report->costCentersUpdated,
            $report->costCentersLinkedToParent
        ));

        if (! empty($report->orphanWarnings)) {
            $this->command?->warn(
                'Avisos de órfãos ('.count($report->orphanWarnings).') — esperado em fixture reduzida.'
            );
        }

        if (! empty($report->readErrors)) {
            $this->command?->error(
                'Erros de leitura ('.count($report->readErrors).'):'
            );
            foreach ($report->readErrors as $err) {
                $this->command?->line("  · {$err}");
            }
        }
    }
}
