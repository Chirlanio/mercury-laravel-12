<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Enums\ReturnStatus;
use App\Models\ReturnOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ReturnOrderStaleAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Envia alerta consolidado para usuários com PROCESS_RETURNS quando há
 * devoluções paradas em `awaiting_product` há mais de N dias (default 7).
 *
 * Contexto de negócio: após aprovada, a devolução entra em
 * `awaiting_product` — aguardando o cliente enviar o produto pelos
 * Correios. Se ninguém acompanha, fica parado indefinidamente. Este
 * alerta permite que o atendimento tome ação proativa (contatar cliente
 * ou cancelar a solicitação).
 *
 * Schedule sugerido: daily 09:00 — chega no início do expediente.
 */
class ReturnOrdersStaleAlertCommand extends Command
{
    protected $signature = 'returns:stale-alert
                            {--days=7 : Limite de dias em awaiting_product}';

    protected $description = 'Alerta diário de devoluções aguardando produto há mais de N dias.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $this->info("Return Stale Alert — threshold: {$days} dias");

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
        $this->info("Total de notificações enviadas: {$grandTotal}");

        return self::SUCCESS;
    }

    /**
     * Escaneia o tenant atual e dispara os alertas. Extraído do handle()
     * para ser testável sem precisar do loop de tenants (in-memory SQLite
     * tem 0 tenants).
     *
     * @return int Número de notificações enviadas
     */
    public function scanTenant(int $days): int
    {
        if (! Schema::hasTable('return_orders')) {
            return 0;
        }

        $threshold = now()->subDays($days);

        // Data de entrada em awaiting_product — usamos approved_at como
        // proxy (em awaiting_product, o estorno foi aprovado e está esperando).
        // Se approved_at é null (shouldn't happen mas defensivo), usa created_at.
        $stale = ReturnOrder::query()
            ->notDeleted()
            ->forStatus(ReturnStatus::AWAITING_PRODUCT)
            ->where(function ($q) use ($threshold) {
                $q->where('approved_at', '<=', $threshold)
                  ->orWhere(function ($q2) use ($threshold) {
                      $q2->whereNull('approved_at')
                         ->where('created_at', '<=', $threshold);
                  });
            })
            ->get();

        if ($stale->isEmpty()) {
            $this->line('  Nada atrasado.');
            return 0;
        }

        $sent = 0;
        $byStore = $stale->groupBy('store_code');

        foreach ($byStore as $storeCode => $returns) {
            $recipients = User::all()->filter(function (User $user) use ($storeCode) {
                if (! $user->hasPermissionTo(Permission::PROCESS_RETURNS->value)) {
                    return false;
                }

                return $user->hasPermissionTo(Permission::MANAGE_RETURNS->value)
                    || $user->store_id === $storeCode;
            })->values();

            if ($recipients->isEmpty()) {
                $this->warn("  [{$storeCode}] sem processadores — {$returns->count()} return(s) sem destinatário");
                continue;
            }

            $payload = $returns->map(function (ReturnOrder $r) {
                // Referência temporal: approved_at se existir, senão created_at
                $ref = $r->approved_at ?? $r->created_at;
                return [
                    'id' => $r->id,
                    'invoice_number' => $r->invoice_number,
                    'store_code' => $r->store_code,
                    'customer_name' => $r->customer_name,
                    'amount_items' => (float) $r->amount_items,
                    'type_label' => $r->type?->label() ?? '—',
                    'created_at' => $r->created_at?->toDateTimeString(),
                    'days_pending' => (int) ($ref?->diffInDays(now()) ?? 0),
                ];
            })->values()->all();

            try {
                Notification::send(
                    $recipients,
                    new ReturnOrderStaleAlertNotification($payload, $this->option('days') ? (int) $this->option('days') : 7)
                );
                $sent += $recipients->count();
                $this->line("  [{$storeCode}] {$returns->count()} devoluções → {$recipients->count()} processadores notificados");
            } catch (\Throwable $e) {
                $this->warn("  [{$storeCode}] falha ao notificar: {$e->getMessage()}");
            }
        }

        return $sent;
    }
}
