<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\DamagedProductMatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Roda matching completo (full scan) em cada tenant. Útil pra captar
 * matches viáveis após mudança de regras de marca/rede ou cadastro de
 * novos tipos de dano que reaproveitem registros antigos.
 *
 * Já rodamos matching pós-create no controller — esse comando garante
 * idempotência e re-tentativas pra registros que ficaram órfãos.
 *
 * Schedule sugerido: daily 06:00 — antes do expediente das lojas.
 */
class DamagedProductsRunMatchingCommand extends Command
{
    protected $signature = 'damaged-products:run-matching';

    protected $description = 'Executa a engine completa de matching de produtos avariados em todos os tenants.';

    public function __construct(protected DamagedProductMatchingService $matching)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $grandScanned = 0;
        $grandCreated = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use (&$grandScanned, &$grandCreated) {
                    $stats = $this->scanTenant();
                    $grandScanned += $stats['scanned'];
                    $grandCreated += $stats['matches_created'];
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total: {$grandScanned} produtos varridos, {$grandCreated} novos matches.");

        return self::SUCCESS;
    }

    /**
     * Roda matching no tenant atual. Extraído do handle() para ser
     * testável sem o loop de tenants.
     *
     * @return array{scanned:int,matches_created:int}
     */
    public function scanTenant(): array
    {
        if (! Schema::hasTable('damaged_products')) {
            return ['scanned' => 0, 'matches_created' => 0];
        }

        $stats = $this->matching->runFullMatching();
        $this->line("  {$stats['scanned']} varridos, {$stats['matches_created']} novos matches");

        return $stats;
    }
}
