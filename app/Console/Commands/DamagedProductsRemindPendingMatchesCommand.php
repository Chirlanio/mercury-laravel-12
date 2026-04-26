<?php

namespace App\Console\Commands;

use App\Enums\DamageMatchStatus;
use App\Enums\Permission;
use App\Models\DamagedProductMatch;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\DamagedProductMatchFoundNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Lembra usuários com APPROVE_DAMAGED_PRODUCT_MATCHES das lojas envolvidas
 * sobre matches em status `pending` há mais de N dias (default 3) sem ação.
 *
 * Reutiliza DamagedProductMatchFoundNotification (a payload é a mesma);
 * o destinatário interpreta pela contagem (sino mostra múltiplos).
 *
 * Schedule sugerido: daily 09:00 — chega no início do expediente.
 */
class DamagedProductsRemindPendingMatchesCommand extends Command
{
    protected $signature = 'damaged-products:remind-pending-matches
                            {--days=3 : Threshold de dias em pending}';

    protected $description = 'Lembrete diário de matches pendentes há mais de N dias sem ação.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $this->info("Damaged Products Remind Pending Matches — threshold: {$days} dias");

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
        $this->info("Total de lembretes enviados: {$grandTotal}");

        return self::SUCCESS;
    }

    /**
     * Escaneia o tenant atual. Extraído do handle() para ser testável
     * sem o loop de tenants.
     *
     * @return int Número de notificações enviadas
     */
    public function scanTenant(int $days): int
    {
        if (! Schema::hasTable('damaged_product_matches')) {
            return 0;
        }

        $threshold = now()->subDays($days);

        $stale = DamagedProductMatch::query()
            ->with(['productA.store', 'productB.store', 'suggestedOriginStore', 'suggestedDestinationStore'])
            ->where('status', DamageMatchStatus::PENDING->value)
            ->where('created_at', '<=', $threshold)
            ->get();

        if ($stale->isEmpty()) {
            $this->line('  Nada pendente.');

            return 0;
        }

        $sent = 0;

        foreach ($stale as $match) {
            $originCode = $match->suggestedOriginStore?->code;
            $destinationCode = $match->suggestedDestinationStore?->code;

            $recipients = User::query()
                ->whereNotNull('email')
                ->whereIn('store_id', array_filter([$originCode, $destinationCode]))
                ->get()
                ->filter(fn (User $u) => $u->hasPermissionTo(Permission::APPROVE_DAMAGED_PRODUCT_MATCHES->value))
                ->unique('id')
                ->values();

            if ($recipients->isEmpty()) {
                continue;
            }

            try {
                Notification::send($recipients, new DamagedProductMatchFoundNotification(
                    match: $match,
                    sendMail: true, // Reforço — destino precisa decidir
                ));
                $sent += $recipients->count();
            } catch (\Throwable $e) {
                $this->warn("  Match #{$match->id}: falha ao notificar — {$e->getMessage()}");
            }
        }

        $this->line("  {$stale->count()} matches pendentes → {$sent} notificações enviadas");

        return $sent;
    }
}
