<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\PurchaseOrderCigamMatcherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Roda o matcher CIGAM em todas as ordens ativas de todos os tenants.
 *
 * Idempotente: o matcher pula movements já vinculados (UNIQUE no
 * matched_movement_id). Pode ser executado várias vezes ao dia sem
 * efeitos colaterais.
 *
 * Schedule sugerido: depois do movements:sync (que roda everyFiveMinutes
 * pra hoje e dailyAt 06:00 pra full), agendamos este every 15 min pra
 * pegar receipts logo após cada sync.
 */
class PurchaseOrderCigamMatchCommand extends Command
{
    protected $signature = 'purchase-orders:cigam-match
                            {--dry-run : Lista o que seria criado sem persistir}';

    protected $description = 'Detecta movimentos CIGAM (code=17) e cria recebimentos automáticos para ordens de compra abertas.';

    public function handle(PurchaseOrderCigamMatcherService $matcher): int
    {
        $this->info('CIGAM Match — varrendo tenants...');

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');
            return self::SUCCESS;
        }

        $totalReceipts = 0;
        $totalItems = 0;
        $totalOrders = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($matcher, &$totalReceipts, &$totalItems, &$totalOrders) {
                    if (! Schema::hasTable('purchase_orders')) {
                        $this->warn('  purchase_orders não encontrada — pulando');
                        return;
                    }

                    $result = $matcher->matchAllActive();
                    $totalOrders += $result['orders_processed'];
                    $totalReceipts += $result['receipts_created'];
                    $totalItems += $result['items_matched'];

                    $this->line("  Ordens varridas: {$result['orders_processed']} · Receipts: {$result['receipts_created']} · Items: {$result['items_matched']}");
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total: {$totalOrders} ordens processadas, {$totalReceipts} receipts criados, {$totalItems} items casados.");

        return self::SUCCESS;
    }
}
