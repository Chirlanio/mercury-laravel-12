<?php

namespace App\Console\Commands;

use App\Services\DRE\ChartOfAccountsImporter;
use Illuminate\Console\Command;

/**
 * DRE — importa o plano de contas + centros de custo a partir de um
 * XLSX do ERP (CIGAM).
 *
 * Uso:
 *   php artisan dre:import-chart <path>
 *   php artisan dre:import-chart <path> --source=TAYLOR
 *   php artisan dre:import-chart <path> --dry-run
 *
 * Output é todo em PT-BR. A UI de upload ainda não existe (virá em
 * prompt posterior); por ora o importador é só via CLI/seeder.
 */
class DreImportChartCommand extends Command
{
    protected $signature = 'dre:import-chart
                            {path : Caminho do arquivo XLSX do plano de contas}
                            {--source=CIGAM : ERP de origem (CIGAM, TAYLOR, ZZNET)}
                            {--dry-run : Simula o import sem persistir nada}';

    protected $description = 'Importa o plano de contas e centros de custo a partir de um XLSX do ERP';

    public function handle(ChartOfAccountsImporter $importer): int
    {
        $path = $this->argument('path');
        $source = (string) $this->option('source');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $this->info("Lendo arquivo {$path}…");
        if ($dryRun) {
            $this->warn('Modo DRY-RUN ativo — nada será persistido.');
        }

        $report = $importer->import($path, $source, $dryRun);

        $prefix = $dryRun ? 'Simularia' : 'Processou';

        $this->newLine();
        $this->line("Processadas {$report->totalRead} linhas do arquivo.");
        if ($report->ignoredMasterRow > 0) {
            $this->line("  Linha-mestre ignorada: {$report->ignoredMasterRow}");
        }

        $this->newLine();
        $this->line('<options=bold>Contas contábeis (chart_of_accounts)</options=bold>');
        $this->line("  {$prefix} {$report->accountsCreated} novas + {$report->accountsUpdated} atualizações.");
        if ($report->accountsDeactivatedByRemoval > 0) {
            $this->line("  {$prefix} desativar {$report->accountsDeactivatedByRemoval} que sumiram do arquivo (is_active=false).");
        }
        if (! $dryRun) {
            $this->line("  Resolvidos parent_id: {$report->accountsLinkedToParent}.");
        }

        $this->newLine();
        $this->line('<options=bold>Centros de custo (cost_centers)</options=bold>');
        $this->line("  {$prefix} {$report->costCentersCreated} novos + {$report->costCentersUpdated} atualizações.");
        if ($report->costCentersDeactivatedByRemoval > 0) {
            $this->line("  {$prefix} desativar {$report->costCentersDeactivatedByRemoval} que sumiram do arquivo.");
        }
        if (! $dryRun) {
            $this->line("  Resolvidos parent_id: {$report->costCentersLinkedToParent}.");
        }

        $this->newLine();
        $this->line('<options=bold>Quebra por V_Grupo</options=bold>');
        foreach ($report->breakdownByGroup() as $g => $count) {
            $label = match ($g) {
                1 => 'Ativo',
                2 => 'Passivo',
                3 => 'Receitas',
                4 => 'Custos e Despesas',
                5 => 'Resultado',
                8 => 'Centros de Custo',
                default => '(desconhecido)',
            };
            $this->line(sprintf('  %d — %-18s %6d', $g, $label, $count));
        }

        if (! empty($report->orphanWarnings)) {
            $this->newLine();
            $this->warn('Avisos — contas analíticas órfãs ('.count($report->orphanWarnings).'):');
            foreach (array_slice($report->orphanWarnings, 0, 10) as $warning) {
                $this->line("  · {$warning}");
            }
            $extra = count($report->orphanWarnings) - 10;
            if ($extra > 0) {
                $this->line("  · (+ {$extra} warnings similares)");
            }
        }

        if (! empty($report->readErrors)) {
            $this->newLine();
            $this->error('Erros de leitura ('.count($report->readErrors).'):');
            foreach (array_slice($report->readErrors, 0, 20) as $error) {
                $this->line("  · {$error}");
            }
            $extra = count($report->readErrors) - 20;
            if ($extra > 0) {
                $this->line("  · (+ {$extra} erros similares)");
            }
        }

        $this->newLine();
        if (empty($report->readErrors)) {
            $this->info($dryRun ? 'Dry-run concluído sem erros.' : 'Importação concluída.');
        } else {
            $errCount = count($report->readErrors);
            $this->warn($dryRun
                ? "Dry-run concluído com {$errCount} erros de leitura (linhas afetadas não seriam persistidas)."
                : 'Importação concluída com erros de leitura — linhas afetadas foram puladas.');
        }

        return self::SUCCESS;
    }
}
