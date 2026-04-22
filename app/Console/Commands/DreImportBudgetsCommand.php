<?php

namespace App\Console\Commands;

use App\Services\DRE\DreBudgetsImporter;
use Illuminate\Console\Command;

/**
 * DRE — importa orçado manual de um XLSX para `dre_budgets`.
 *
 * Uso:
 *   php artisan dre:import-budgets <path> --version=2026.v1
 *   php artisan dre:import-budgets <path> --version=action_plan_v1 --dry-run
 *
 * Formato documentado em `docs/dre-imports-formatos.md`.
 */
class DreImportBudgetsCommand extends Command
{
    protected $signature = 'dre:import-budgets
                            {path : Caminho do arquivo XLSX}
                            {--version= : Label da versão do orçamento (ex: 2026.v1)}
                            {--dry-run : Simula sem persistir}';

    protected $description = 'Importa orçado manual para dre_budgets';

    public function handle(DreBudgetsImporter $importer): int
    {
        $path = (string) $this->argument('path');
        $version = (string) ($this->option('version') ?? '');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        if ($version === '') {
            $this->error('--version=<label> é obrigatório (ex: 2026.v1, action_plan_v1).');

            return self::FAILURE;
        }

        $this->info("Lendo arquivo {$path} (budget_version={$version})…");
        if ($dryRun) {
            $this->warn('Modo DRY-RUN ativo — nada será persistido.');
        }

        $report = $importer->import($path, $version, $dryRun);

        $this->newLine();
        $this->line("Processadas {$report->totalRead} linhas.");
        $this->line("  Criadas: {$report->created}");
        $this->line("  Puladas: {$report->skipped}");

        if (! empty($report->errors)) {
            $this->newLine();
            $this->error('Erros ('.count($report->errors).'):');
            foreach (array_slice($report->errors, 0, 20) as $err) {
                $this->line("  · {$err}");
            }
            $extra = count($report->errors) - 20;
            if ($extra > 0) {
                $this->line("  · (+ {$extra} erros similares)");
            }
        }

        $this->newLine();
        if (empty($report->errors)) {
            $this->info($dryRun ? 'Dry-run concluído sem erros.' : 'Importação concluída.');
        } else {
            $this->warn('Concluído com erros — linhas afetadas foram puladas.');
        }

        return self::SUCCESS;
    }
}
