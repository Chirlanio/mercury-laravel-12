<?php

namespace App\Console\Commands;

use App\Services\DRE\ActionPlanHintImporter;
use Illuminate\Console\Command;

/**
 * Popula `chart_of_accounts.default_management_class_id` a partir do
 * Action Plan XLSX. Usado como hint visual na tela de Pendências do DRE.
 *
 * Uso:
 *   php artisan dre:import-action-plan-hints <path-to-xlsx>
 *   php artisan tenants:run dre:import-action-plan-hints --argument='path=...'
 *
 * Output em PT. Arquivo ausente resulta em skip silencioso com exit 0 —
 * comportamento defensivo para ambientes sem o XLSX.
 */
class DreImportActionPlanHintsCommand extends Command
{
    protected $signature = 'dre:import-action-plan-hints
                            {path : Caminho do Action Plan XLSX}';

    protected $description = 'Popula default_management_class_id em chart_of_accounts a partir do Action Plan';

    public function handle(ActionPlanHintImporter $importer): int
    {
        $path = $this->argument('path');

        $this->info("Lendo Action Plan de {$path}…");

        $report = $importer->populateDefaultManagementClass($path);

        if ($report->fileNotFound) {
            $this->warn("Arquivo não encontrado em {$path}. Nenhuma alteração feita.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("Linhas lidas: {$report->totalRowsRead}");
        $this->line("Pares únicos (conta, classe gerencial): {$report->uniquePairsFound}");

        $this->newLine();
        $this->line('<options=bold>Resultado</options=bold>');
        $this->line("  Contas atualizadas com hint: {$report->accountsUpdated}");
        $this->line("  Contas puladas (hint já presente): {$report->accountsSkippedAlreadyHinted}");
        $this->line("  Contas não encontradas no plano: {$report->accountsNotFound}");
        $this->line("  Classes gerenciais não encontradas: {$report->managementClassesNotFound}");

        if (! empty($report->missingAccountCodes)) {
            $this->newLine();
            $this->warn('Amostra de contas ausentes (primeiras 20):');
            foreach (array_slice($report->missingAccountCodes, 0, 20) as $code) {
                $this->line("  · {$code}");
            }
        }

        if (! empty($report->missingManagementClassCodes)) {
            $this->newLine();
            $this->warn('Amostra de classes gerenciais ausentes (primeiras 20):');
            foreach (array_slice($report->missingManagementClassCodes, 0, 20) as $code) {
                $this->line("  · {$code}");
            }
        }

        $this->newLine();
        $this->info('Concluído.');

        return self::SUCCESS;
    }
}
