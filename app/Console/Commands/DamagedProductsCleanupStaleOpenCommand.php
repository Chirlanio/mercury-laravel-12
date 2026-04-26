<?php

namespace App\Console\Commands;

use App\Enums\DamagedProductStatus;
use App\Models\DamagedProduct;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Alerta (não cancela) sobre registros em status `open` há mais de N
 * dias (default 60) sem nenhum match encontrado. Sinaliza ajuste de
 * cadastro de marca/rede ou re-revisão da regra de matching pelo admin.
 *
 * Em vez de notificação por usuário (que daria muito ruído), apenas
 * imprime no console — admins acompanham via cron logs ou rodam
 * manualmente quando precisam de visão consolidada.
 *
 * Schedule sugerido: weekly seg 02:00 — relatório de início de semana.
 */
class DamagedProductsCleanupStaleOpenCommand extends Command
{
    protected $signature = 'damaged-products:cleanup-stale-open
                            {--days=60 : Threshold de dias em open}';

    protected $description = 'Relatório semanal de produtos avariados em open há mais de N dias sem match.';

    public function handle(): int
    {
        $days = max(7, (int) $this->option('days'));
        $this->info("Damaged Products Stale Open Report — threshold: {$days} dias");

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $grandTotal = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($days, &$grandTotal) {
                    $grandTotal += $this->scanTenant($days);
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total de registros stale identificados: {$grandTotal}");

        return self::SUCCESS;
    }

    /**
     * Reporta no tenant atual. Extraído do handle() para ser testável
     * sem o loop de tenants. Não muta dados — apenas observa.
     */
    public function scanTenant(int $days): int
    {
        if (! Schema::hasTable('damaged_products')) {
            return 0;
        }

        $threshold = now()->subDays($days);

        $stale = DamagedProduct::query()
            ->with('store:id,code,name')
            ->where('status', DamagedProductStatus::OPEN->value)
            ->where('created_at', '<=', $threshold)
            ->get();

        if ($stale->isEmpty()) {
            $this->line('  Nada stale.');

            return 0;
        }

        $byStore = $stale->groupBy(fn ($p) => $p->store?->code ?? '—');
        foreach ($byStore as $code => $items) {
            $this->line("  {$code}: {$items->count()} registro(s) em open há {$days}+ dias");
            foreach ($items->take(5) as $item) {
                $age = (int) now()->diffInDays($item->created_at);
                $this->line("    #{$item->id} {$item->product_reference} ({$age}d)");
            }
            if ($items->count() > 5) {
                $this->line('    ... e mais ' . ($items->count() - 5) . ' registro(s)');
            }
        }

        return $stale->count();
    }
}
