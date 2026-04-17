<?php

namespace App\Console\Commands;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Services\PurchaseOrderTransitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Reconcilia ordens travadas em INVOICED/PARTIAL_INVOICED cujos itens já
 * foram todos recebidos. Existe porque o matcher CIGAM passou um bom
 * tempo rodando com autoTransitionAfterReceipt pulando a transição quando
 * actor era null — ordens com 100% recebido via CIGAM ficaram no status
 * anterior.
 *
 * Usa o created_by_user_id da própria ordem como actor da transição
 * (mesmo fallback do ReceiptService após o fix). Se esse usuário não tiver
 * RECEIVE_PURCHASE_ORDERS, a transição é pulada e reportada.
 *
 * Idempotente: só afeta ordens que ainda não transicionaram.
 */
class PurchaseOrderReconcileDeliveredCommand extends Command
{
    protected $signature = 'purchase-orders:reconcile-delivered
                            {--dry-run : Lista o que seria transicionado sem persistir}';

    protected $description = 'Transiciona ordens para DELIVERED quando todos os itens já foram recebidos mas o status não foi atualizado.';

    public function handle(PurchaseOrderTransitionService $transitionService): int
    {
        $this->info('Reconciliando ordens com recebimento total mas status pendente...');

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $totalTransitioned = 0;
        $totalSkipped = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($transitionService, $isDryRun, &$totalTransitioned, &$totalSkipped) {
                    if (! Schema::hasTable('purchase_orders')) {
                        $this->warn('  purchase_orders não encontrada — pulando');

                        return;
                    }

                    $candidates = PurchaseOrder::query()
                        ->with(['items', 'createdBy'])
                        ->notDeleted()
                        ->whereIn('status', [
                            PurchaseOrderStatus::INVOICED->value,
                            PurchaseOrderStatus::PARTIAL_INVOICED->value,
                        ])
                        ->get();

                    foreach ($candidates as $order) {
                        $ordered = (int) $order->items->sum('quantity_ordered');
                        $received = (int) $order->items->sum('quantity_received');

                        if ($ordered === 0 || $received < $ordered) {
                            continue;
                        }

                        $actor = $order->createdBy;
                        if (! $actor) {
                            $this->warn("  #{$order->order_number}: sem createdBy — pulando");
                            $totalSkipped++;

                            continue;
                        }

                        if ($isDryRun) {
                            $this->line("  [dry-run] #{$order->order_number} ({$order->status->value} → delivered) — {$received}/{$ordered}");
                            $totalTransitioned++;

                            continue;
                        }

                        try {
                            $transitionService->transition(
                                $order,
                                PurchaseOrderStatus::DELIVERED,
                                $actor,
                                'Reconciliação: recebimento total detectado em backfill'
                            );
                            $this->line("  ✓ #{$order->order_number} → delivered ({$received}/{$ordered})");
                            $totalTransitioned++;
                        } catch (\Throwable $e) {
                            $this->warn("  #{$order->order_number}: falha — {$e->getMessage()}");
                            $totalSkipped++;
                        }
                    }
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $prefix = $isDryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Total: {$totalTransitioned} transicionadas, {$totalSkipped} puladas.");

        return self::SUCCESS;
    }
}
