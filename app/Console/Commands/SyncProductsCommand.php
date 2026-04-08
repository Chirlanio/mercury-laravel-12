<?php

namespace App\Console\Commands;

use App\Models\ProductSyncLog;
use App\Models\Tenant;
use App\Services\ProductSyncService;
use Illuminate\Console\Command;

class SyncProductsCommand extends Command
{
    protected $signature = 'products:sync
                           {type=full : Tipo de sync (full, incremental, by_period, lookups_only, prices_only)}
                           {--tenant= : Tenant específico}
                           {--log-id= : ID do ProductSyncLog pré-criado}
                           {--user-id= : ID do usuário que iniciou}
                           {--date-start= : Data início para by_period}
                           {--date-end= : Data fim para by_period}';

    protected $description = 'Sincroniza produtos do CIGAM';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");
            $tenant->run(fn () => $this->processSync());
        }

        return self::SUCCESS;
    }

    protected function processSync(): void
    {
        $service = app(ProductSyncService::class);
        $type = $this->argument('type');
        $dateStart = $this->option('date-start');
        $dateEnd = $this->option('date-end');
        $userId = $this->option('user-id') ? (int) $this->option('user-id') : null;

        if (! $service->isAvailable()) {
            $this->error('  CIGAM indisponível: ' . ($service->getUnavailableReason ?? 'driver não encontrado'));
            $this->failLog('CIGAM indisponível');

            return;
        }

        // Use pre-created log or create new one
        $log = $this->resolveLog();

        if (! $log) {
            $log = $service->initSync($type, $userId, $dateStart, $dateEnd);
        }

        try {
            // Phase 1: Lookups
            if ($type !== 'prices_only') {
                $log->update(['current_phase' => 'lookups']);
                $this->line('  Sincronizando tabelas auxiliares...');
                $service->syncLookups();
            }

            // Phase 2: Product chunks
            if (! in_array($type, ['prices_only', 'lookups_only'])) {
                $log->update(['current_phase' => 'products']);
                $this->line('  Sincronizando produtos...');
                $lastReference = null;
                $hasMore = true;
                $chunkCount = 0;

                while ($hasMore) {
                    $result = $service->processChunk($log->id, $lastReference, 500, $dateStart, $dateEnd);
                    $hasMore = $result['has_more'];
                    $lastReference = $result['last_reference'] ?? null;
                    $chunkCount++;

                    if ($chunkCount % 10 === 0) {
                        $log->refresh();
                        $this->line("    Chunk {$chunkCount}: {$log->processed_records}/{$log->total_records} processados");
                    }
                }
            }

            // Phase 3: Prices
            if ($type !== 'lookups_only') {
                $log->update(['current_phase' => 'prices']);
                $this->line('  Sincronizando preços...');
                $service->syncPrices($log->id, $dateStart, $dateEnd);
            }

            // Finalize
            $service->finalizeSync($log->id);
            $log->refresh();
            $this->info("  Concluído: {$log->inserted_records} inseridos, {$log->updated_records} atualizados, {$log->skipped_records} ignorados");
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            $this->error("  Falha: {$e->getMessage()}");
        }
    }

    protected function resolveLog(): ?ProductSyncLog
    {
        $logId = $this->option('log-id');

        return $logId ? ProductSyncLog::find((int) $logId) : null;
    }

    protected function failLog(string $message): void
    {
        $log = $this->resolveLog();
        if ($log && $log->status === 'running') {
            $log->markFailed($message);
        }
    }
}
