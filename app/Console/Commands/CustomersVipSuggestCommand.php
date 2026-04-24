<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\CustomerVipClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Roda a classificação automática VIP para um ano em todos os tenants.
 *
 * Uso manual:
 *   php artisan customers:vip-suggest              (ano corrente)
 *   php artisan customers:vip-suggest --year=2024  (ano específico)
 *
 * Schedule: weekly (segunda 05:30) para o ano corrente. Curadorias
 * existentes são sempre preservadas — só os snapshots (total_revenue,
 * total_orders, suggested_tier) são refrescados.
 *
 * Idempotente: roda quantas vezes quiser no mesmo dia sem efeito colateral.
 */
class CustomersVipSuggestCommand extends Command
{
    protected $signature = 'customers:vip-suggest {--year= : Ano a classificar (default: ano corrente)}';

    protected $description = 'Gera sugestões automáticas de VIPs Black/Gold a partir do faturamento em movements.';

    public function handle(CustomerVipClassificationService $classifier): int
    {
        $year = (int) ($this->option('year') ?: now()->year);

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $this->info("Classificando VIPs — ano {$year}");
        $this->newLine();

        $totals = ['processed' => 0, 'black' => 0, 'gold' => 0, 'preserved' => 0];

        foreach ($tenants as $tenant) {
            $this->line("Tenant: {$tenant->id}");
            try {
                $tenant->run(function () use ($classifier, $year, &$totals) {
                    $result = $this->runOnTenant($classifier, $year);
                    if ($result === null) {
                        $this->line('  Tabelas VIP ausentes — tenant ignorado.');

                        return;
                    }
                    $this->line(sprintf(
                        '  %d clientes processados: %d Black, %d Gold, %d abaixo do threshold, %d preservaram curadoria.',
                        $result['processed'],
                        $result['suggested_black'],
                        $result['suggested_gold'],
                        $result['below_threshold'],
                        $result['preserved_curated'],
                    ));
                    $totals['processed'] += $result['processed'];
                    $totals['black'] += $result['suggested_black'];
                    $totals['gold'] += $result['suggested_gold'];
                    $totals['preserved'] += $result['preserved_curated'];
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Totalização: %d processados · %d Black · %d Gold · %d curadorias preservadas.',
            $totals['processed'], $totals['black'], $totals['gold'], $totals['preserved'],
        ));

        return self::SUCCESS;
    }

    /**
     * Roda no tenant atual. Retorna null se as tabelas VIP não estiverem
     * migradas no tenant (caso um tenant antigo não tenha rodado migrate).
     *
     * @return array{processed:int, suggested_black:int, suggested_gold:int, below_threshold:int, preserved_curated:int, year:int}|null
     */
    public function runOnTenant(CustomerVipClassificationService $classifier, int $year): ?array
    {
        if (! Schema::hasTable('customer_vip_tiers')) {
            return null;
        }
        if (! Schema::hasTable('movements')) {
            return null;
        }

        return $classifier->generateSuggestions($year);
    }
}
