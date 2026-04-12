<?php

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Models\Tenant;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

class GeocodeDeliveriesCommand extends Command
{
    protected $signature = 'deliveries:geocode
                           {--force : Re-geocodificar todas, mesmo as já geocodificadas}
                           {--dry-run : Apenas listar entregas sem geocodificar}
                           {--limit=0 : Limitar quantidade de entregas}';

    protected $description = 'Geocodifica endereços de entregas usando Nominatim (OpenStreetMap)';

    public function handle(GeocodingService $service): int
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($service, $force, $dryRun, $limit) {
                    $query = Delivery::active()
                        ->where(function ($q) {
                            $q->whereNotNull('address')->orWhereNotNull('neighborhood');
                        });

                    if (! $force) {
                        $query->whereNull('geocoded_at');
                    }

                    if ($limit > 0) {
                        $query->limit($limit);
                    }

                    $deliveries = $query->get();

                    $this->line("  {$deliveries->count()} entregas para geocodificar");

                    if ($dryRun) {
                        foreach ($deliveries as $d) {
                            $this->line("  [{$d->id}] {$d->address}, {$d->neighborhood}");
                        }

                        return;
                    }

                    if ($deliveries->isEmpty()) {
                        return;
                    }

                    $result = $service->geocodeBatch($deliveries);
                    $this->info("  Sucesso: {$result['success']}, Falhas: {$result['failed']}");
                });
            } catch (\Exception $e) {
                $this->error("  Erro: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
