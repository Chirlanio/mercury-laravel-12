<?php

namespace App\Console\Commands;

use App\Models\MovementSyncLog;
use App\Models\Tenant;
use App\Services\MovementSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncMovementsCommand extends Command
{
    protected $signature = 'movements:sync
                           {mode=auto : Modo de sync (auto, today, month, range)}
                           {--month= : Mes para sync por mes}
                           {--year= : Ano para sync por mes}
                           {--start= : Data inicio para sync por periodo}
                           {--end= : Data fim para sync por periodo}
                           {--tenant= : Tenant especifico (padrao: todos)}
                           {--log-id= : ID do MovementSyncLog pre-criado}
                           {--user-id= : ID do usuario que iniciou}';

    protected $description = 'Sincroniza movimentacoes do CIGAM para a tabela movements';

    public function handle(): int
    {
        $mode = $this->argument('mode');
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            $tenant->run(function () use ($mode) {
                $this->processSync($mode);
            });
        }

        return self::SUCCESS;
    }

    protected function processSync(string $mode): void
    {
        $service = app(MovementSyncService::class);

        if (! $service->isAvailable()) {
            $this->error('  CIGAM indisponivel: ' . $service->getUnavailableReason());

            // If we have a pre-created log, mark it as failed
            $this->failLog($service->getUnavailableReason() ?? 'CIGAM indisponivel');

            return;
        }

        $existingLog = $this->resolveLog();
        $userId = $this->option('user-id') ? (int) $this->option('user-id') : null;

        $log = match ($mode) {
            'today' => $service->syncToday($userId, $existingLog),
            'month' => $this->syncMonth($service, $userId, $existingLog),
            'range' => $this->syncRange($service, $userId, $existingLog),
            default => $service->syncAutomatic($userId, $existingLog),
        };

        if ($log->status === 'failed') {
            $this->error("  Falha: " . ($log->error_details['message'] ?? 'Erro desconhecido'));

            return;
        }

        $this->info("  Concluido: {$log->inserted_records} inseridos, {$log->deleted_records} removidos, {$log->error_count} erros");
    }

    protected function syncMonth(MovementSyncService $service, ?int $userId, ?MovementSyncLog $existingLog)
    {
        $month = (int) ($this->option('month') ?: now()->month);
        $year = (int) ($this->option('year') ?: now()->year);
        $this->line("  Sync mes: {$month}/{$year}");

        return $service->syncByMonth($month, $year, $userId, $existingLog);
    }

    protected function syncRange(MovementSyncService $service, ?int $userId, ?MovementSyncLog $existingLog)
    {
        $start = $this->option('start');
        $end = $this->option('end');

        if (! $start || ! $end) {
            $this->error('  --start e --end sao obrigatorios para modo range');

            return $service->syncAutomatic($userId, $existingLog); // fallback
        }

        $this->line("  Sync periodo: {$start} a {$end}");

        return $service->syncByDateRange(Carbon::parse($start), Carbon::parse($end), $userId, $existingLog);
    }

    protected function resolveLog(): ?MovementSyncLog
    {
        $logId = $this->option('log-id');

        if (! $logId) {
            return null;
        }

        return MovementSyncLog::find((int) $logId);
    }

    protected function failLog(string $message): void
    {
        $log = $this->resolveLog();

        if ($log && $log->status === 'running') {
            $log->markFailed($message);
        }
    }
}
