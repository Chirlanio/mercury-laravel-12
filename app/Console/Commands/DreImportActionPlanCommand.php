<?php

namespace App\Console\Commands;

use App\Services\DRE\ActionPlanImporter;
use Illuminate\Console\Command;

/**
 * Importa o Action Plan (primeiro orçamento 2026 do Grupo Meia Sola) para
 * `dre_budgets` com `budget_version='action_plan_v1'` (configurável).
 *
 * Uso:
 *   php artisan dre:import-action-plan
 *   php artisan dre:import-action-plan --file="Action Plan v2.xlsx" --version=action_plan_v2
 *   php artisan dre:import-action-plan --dry-run
 *
 * Arquivo default: `storage/app/imports/Action Plan v1.xlsx` (CFO coloca
 * via SFTP/S3/upload manual). O playbook do prompt 10.5 declara execução
 * via SSH/queue, não HTTP — por isso não há rota pareada.
 */
class DreImportActionPlanCommand extends Command
{
    protected $signature = 'dre:import-action-plan
                            {--file= : Caminho do XLSX (default: storage/app/imports/Action Plan v1.xlsx)}
                            {--version=action_plan_v1 : Label da versão do orçamento}
                            {--dry-run : Simula sem persistir}';

    protected $description = 'Importa o Action Plan para dre_budgets (idempotente por chave composta)';

    public function handle(ActionPlanImporter $importer): int
    {
        $path = (string) ($this->option('file')
            ?: storage_path('app/imports/Action Plan v1.xlsx'));
        $version = (string) $this->option('version');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");
            $this->line('  Dica: coloque o XLSX em storage/app/imports/ ou passe --file=.');

            return self::FAILURE;
        }

        $this->info("Lendo arquivo {$path} (budget_version={$version})…");
        if ($dryRun) {
            $this->warn('Modo DRY-RUN ativo — nada será persistido.');
        }

        $report = $importer->import($path, $version, $dryRun);

        $this->newLine();
        $this->line("Processando {$report->totalRead} linhas…");
        $this->line("  Inseridas: {$report->inserted}");
        $this->line("  Atualizadas (reimport): {$report->updated}");
        $this->line("  Puladas: {$report->skipped}");

        if (! empty($report->errors)) {
            $this->newLine();
            $this->warn('Erros ('.count($report->errors).') — primeiras 20:');
            foreach (array_slice($report->errors, 0, 20) as $err) {
                $this->line("  · {$err}");
            }
            $extra = count($report->errors) - 20;
            if ($extra > 0) {
                $this->line("  · (+ {$extra} erros similares)");
            }
        }

        $this->newLine();
        $suffix = $report->skipped > 0
            ? " ({$report->skipped} com erros — ver relatório)"
            : '';
        $this->info(
            $dryRun
                ? "Dry-run concluído: {$report->inserted} seriam inseridas, {$report->updated} seriam atualizadas{$suffix}."
                : "Importação concluída: {$report->inserted} novas + {$report->updated} atualizadas{$suffix}."
        );

        return self::SUCCESS;
    }
}
