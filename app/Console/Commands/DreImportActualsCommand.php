<?php

namespace App\Console\Commands;

use App\Services\DRE\DreActualsImporter;
use Illuminate\Console\Command;

/**
 * DRE — importa realizado manual (balancete externo, lançamentos fora do ERP)
 * de um XLSX para `dre_actuals` com source=MANUAL_IMPORT.
 *
 * Uso:
 *   php artisan dre:import-actuals <path>
 *   php artisan dre:import-actuals <path> --dry-run
 *
 * Formato documentado em `docs/dre-imports-formatos.md`.
 */
class DreImportActualsCommand extends Command
{
    protected $signature = 'dre:import-actuals
                            {path : Caminho do arquivo XLSX}
                            {--dry-run : Simula sem persistir}';

    protected $description = 'Importa realizado manual para dre_actuals (source=MANUAL_IMPORT)';

    public function handle(DreActualsImporter $importer): int
    {
        $path = (string) $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $this->info("Lendo arquivo {$path}…");
        if ($dryRun) {
            $this->warn('Modo DRY-RUN ativo — nada será persistido.');
        }

        $report = $importer->import($path, $dryRun);

        $this->newLine();
        $this->line("Processadas {$report->totalRead} linhas.");
        $this->line("  Criadas: {$report->created}");
        $this->line("  Atualizadas (upsert por external_id): {$report->updated}");
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
