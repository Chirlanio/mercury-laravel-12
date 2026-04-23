<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\CustomerSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Sincroniza clientes do CIGAM (view msl_dcliente_) para todos os tenants.
 *
 * Uso típico:
 *   php artisan customers:sync                 # full sync (todos os tenants)
 *   php artisan customers:sync --tenant=abc    # apenas 1 tenant
 *   php artisan customers:sync --chunk=1000    # chunk maior
 *
 * Schedule sugerido: dailyAt 04:00 (antes do movements:sync das 06:00).
 * Idempotente por design — upsert pelo cigam_code.
 */
class CustomersSyncCommand extends Command
{
    protected $signature = 'customers:sync
                            {--tenant= : Roda apenas no tenant informado}
                            {--chunk=500 : Tamanho do chunk (1-5000)}';

    protected $description = 'Sincroniza clientes da view CIGAM msl_dcliente_.';

    public function handle(CustomerSyncService $service): int
    {
        $this->info('Customers Sync — varrendo tenants...');

        if (! $service->isAvailable()) {
            $this->error('Conexão CIGAM indisponível — verifique database.cigam.');

            return self::FAILURE;
        }

        $chunkSize = max(1, min(5000, (int) $this->option('chunk')));
        $tenantFilter = $this->option('tenant');

        $tenantsQuery = Tenant::query();
        if ($tenantFilter) {
            $tenantsQuery->where('id', $tenantFilter);
        }

        $tenants = $tenantsQuery->get();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $grandInserted = 0;
        $grandUpdated = 0;
        $grandErrors = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($service, $chunkSize, &$grandInserted, &$grandUpdated, &$grandErrors) {
                    $result = $this->scanTenant($service, $chunkSize);
                    $grandInserted += $result['inserted'];
                    $grandUpdated += $result['updated'];
                    $grandErrors += $result['errors'];
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
                $grandErrors++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Total: %d inseridos · %d atualizados · %d erros',
            $grandInserted, $grandUpdated, $grandErrors,
        ));

        return $grandErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Processa 1 tenant — loop de chunks até has_more=false.
     *
     * @return array{inserted: int, updated: int, errors: int}
     */
    public function scanTenant(CustomerSyncService $service, int $chunkSize = 500): array
    {
        if (! Schema::hasTable('customers') || ! Schema::hasTable('customer_sync_logs')) {
            $this->warn('  Módulo customers não instalado — pulando');

            return ['inserted' => 0, 'updated' => 0, 'errors' => 0];
        }

        $log = $service->start('full');
        $this->line("  Sync #{$log->id} iniciado (total: {$log->total_records})");

        $totalInserted = 0;
        $totalUpdated = 0;
        $lastCode = null;

        while (true) {
            $result = $service->processChunk($log->id, $lastCode, $chunkSize);
            $totalInserted += $result['inserted'];
            $totalUpdated += $result['updated'];

            $this->line(sprintf(
                '    chunk: +%d novos · %d atualizados · %d pulados',
                $result['inserted'], $result['updated'], $result['skipped'],
            ));

            if (! $result['has_more'] || $result['cancelled']) {
                break;
            }

            $lastCode = $result['last_code'];
        }

        $log->refresh();
        $this->info("  Sync #{$log->id} finalizado: {$log->status} — {$totalInserted} novos, {$totalUpdated} atualizados, {$log->error_count} erros");

        return [
            'inserted' => $totalInserted,
            'updated' => $totalUpdated,
            'errors' => $log->error_count,
        ];
    }
}
