<?php

namespace App\Console\Commands;

use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class AuditLogsCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:cleanup
                           {--days=90 : Dias para manter os logs (padrão: 90)}
                           {--dry-run : Executar sem deletar para ver quantos registros seriam removidos}
                           {--force : Executar sem confirmação}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpa logs de auditoria antigos baseado na configuração de retenção';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($days < 1) {
            $this->error('O número de dias deve ser maior que 0');
            return 1;
        }

        $auditService = app(AuditLogService::class);
        $cutoffDate = Carbon::now()->subDays($days);

        // Mostrar informações sobre a operação
        $this->info("Limpeza de logs de auditoria");
        $this->info("Data de corte: {$cutoffDate->format('d/m/Y H:i:s')}");
        $this->info("Logs mais antigos que {$days} dias serão removidos");
        $this->newLine();

        // Contar registros que seriam removidos
        $count = \App\Models\ActivityLog::where('created_at', '<', $cutoffDate)->count();

        if ($count === 0) {
            $this->info('Nenhum log encontrado para remoção.');
            return 0;
        }

        $this->info("Registros encontrados para remoção: {$count}");

        if ($dryRun) {
            $this->warn('MODO DRY-RUN: Nenhum registro será realmente removido.');
            return 0;
        }

        // Confirmação
        if (!$force) {
            if (!$this->confirm("Tem certeza que deseja remover {$count} registros de log?")) {
                $this->info('Operação cancelada.');
                return 0;
            }
        }

        // Executar limpeza
        $this->info('Iniciando limpeza...');

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $deleted = 0;
        $batchSize = 1000;

        while (true) {
            $batch = \App\Models\ActivityLog::where('created_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->delete();

            if ($batch === 0) {
                break;
            }

            $deleted += $batch;
            $bar->advance($batch);
        }

        $bar->finish();
        $this->newLine();

        $this->info("Limpeza concluída! {$deleted} registros foram removidos.");

        // Log da operação
        if (auth()->check()) {
            $auditService->logCustomAction(
                action: 'cleanup',
                description: "Executou limpeza de logs: {$deleted} registros removidos (mais antigos que {$days} dias)",
                metadata: [
                    'deleted_count' => $deleted,
                    'days' => $days,
                    'cutoff_date' => $cutoffDate->toISOString(),
                ]
            );
        }

        return 0;
    }
}
