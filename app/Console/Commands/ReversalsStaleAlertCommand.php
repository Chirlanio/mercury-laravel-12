<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Enums\ReversalStatus;
use App\Models\Reversal;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ReversalStaleAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Envia alerta consolidado para aprovadores quando há estornos parados
 * em pending_authorization há mais de N dias (default 3).
 *
 * Um único email/notification por aprovador, listando todos os estornos
 * atrasados daquela loja (ou todos, se o aprovador tem MANAGE_REVERSALS).
 *
 * Schedule sugerido: daily 09:00 — evita ruído durante a madrugada e
 * garante que chega no início do expediente.
 */
class ReversalsStaleAlertCommand extends Command
{
    protected $signature = 'reversals:stale-alert
                            {--days=3 : Limite de dias em pending_authorization}';

    protected $description = 'Alerta diário de estornos atrasados aguardando autorização.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $this->info("Stale Alert — threshold: {$days} dias");

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
     * Escaneia o tenant atual (qualquer contexto) e dispara os alertas.
     * Extraído do handle() para ser testável sem precisar do loop de
     * tenants.
     *
     * @return int Número de notificações enviadas
     */
    public function scanTenant(int $days): int
    {
        if (! Schema::hasTable('reversals')) {
            return 0;
        }

        $threshold = now()->subDays($days);

        $stale = Reversal::query()
            ->notDeleted()
            ->forStatus(ReversalStatus::PENDING_AUTHORIZATION)
            ->where('created_at', '<=', $threshold)
            ->get();

        if ($stale->isEmpty()) {
            $this->line('  Nada atrasado.');
            return 0;
        }

        $sent = 0;
        $byStore = $stale->groupBy('store_code');

        foreach ($byStore as $storeCode => $reversals) {
            $recipients = User::all()->filter(function (User $user) use ($storeCode) {
                if (! $user->hasPermissionTo(Permission::APPROVE_REVERSALS->value)) {
                    return false;
                }

                return $user->hasPermissionTo(Permission::MANAGE_REVERSALS->value)
                    || $user->store_id === $storeCode;
            })->values();

            if ($recipients->isEmpty()) {
                $this->warn("  [{$storeCode}] sem aprovadores — {$reversals->count()} estorno(s) sem destinatário");
                continue;
            }

            $payload = $reversals->map(fn (Reversal $r) => [
                'id' => $r->id,
                'invoice_number' => $r->invoice_number,
                'store_code' => $r->store_code,
                'customer_name' => $r->customer_name,
                'amount_reversal' => (float) $r->amount_reversal,
                'created_at' => $r->created_at?->toDateTimeString(),
                'days_pending' => (int) $r->created_at?->diffInDays(now()),
            ])->values()->all();

            try {
                Notification::send(
                    $recipients,
                    new ReversalStaleAlertNotification($payload)
                );
                $sent += $recipients->count();
                $this->line("  [{$storeCode}] {$reversals->count()} estornos → {$recipients->count()} aprovadores notificados");
            } catch (\Throwable $e) {
                $this->warn("  [{$storeCode}] falha ao notificar: {$e->getMessage()}");
            }
        }

        return $sent;
    }
}
