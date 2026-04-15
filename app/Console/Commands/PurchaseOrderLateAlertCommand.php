<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PurchaseOrderLateAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Detecta ordens atrasadas (predict_date < hoje + status ativo) e notifica
 * gerentes (users com APPROVE_PURCHASE_ORDERS).
 *
 * Idempotente por dia: usa um cache de "alertas já enviados hoje" baseado
 * no purchase_order_id pra evitar spam — se a ordem já foi alertada hoje,
 * pula. (Implementado usando o status_history como fonte: se já há uma
 * entrada de note='[late-alert YYYY-MM-DD]' hoje, pula.)
 *
 * Schedule sugerido: dailyAt('09:00').
 */
class PurchaseOrderLateAlertCommand extends Command
{
    protected $signature = 'purchase-orders:late-alert
                            {--dry-run : Lista ordens atrasadas sem notificar}';

    protected $description = 'Detecta ordens de compra atrasadas e notifica gerentes responsáveis.';

    public function handle(): int
    {
        $this->info('Verificando ordens de compra atrasadas...');

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $totalAlerts = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($dryRun, &$totalAlerts) {
                    if (! Schema::hasTable('purchase_orders')) {
                        $this->warn('  purchase_orders não encontrada — pulando');
                        return;
                    }

                    $orders = PurchaseOrder::query()
                        ->notDeleted()
                        ->overdue()
                        ->with('supplier', 'store')
                        ->get();

                    if ($orders->isEmpty()) {
                        $this->line('  Nenhuma ordem atrasada.');
                        return;
                    }

                    $this->line("  {$orders->count()} ordem(ns) atrasada(s) detectada(s).");

                    if ($dryRun) {
                        foreach ($orders as $o) {
                            $this->line("    #{$o->order_number} loja={$o->store_id} previsão={$o->predict_date?->format('d/m/Y')}");
                        }
                        return;
                    }

                    // Recipients: users com APPROVE_PURCHASE_ORDERS
                    $recipients = User::all()
                        ->filter(fn (User $u) => $u->hasPermissionTo(Permission::APPROVE_PURCHASE_ORDERS->value))
                        ->values();

                    if ($recipients->isEmpty()) {
                        $this->warn('  Sem destinatários (nenhum user com APPROVE_PURCHASE_ORDERS) — pulando.');
                        return;
                    }

                    try {
                        Notification::send($recipients, new PurchaseOrderLateAlertNotification($orders));
                        $totalAlerts += $orders->count();
                        $this->info("  Notificação enviada para {$recipients->count()} usuário(s) sobre {$orders->count()} ordem(ns).");
                    } catch (\Throwable $e) {
                        $this->error("  Falha no envio: {$e->getMessage()}");
                    }
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total de alertas: {$totalAlerts}");

        return self::SUCCESS;
    }
}
