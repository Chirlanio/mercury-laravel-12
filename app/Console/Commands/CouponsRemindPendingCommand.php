<?php

namespace App\Console\Commands;

use App\Enums\CouponStatus;
use App\Enums\Permission;
use App\Models\Coupon;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\CouponStatusChangedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Envia lembrete consolidado para usuários com ISSUE_COUPON_CODE (equipe
 * e-commerce) quando há cupons em `requested` há mais de N dias (default 3).
 *
 * Contexto: cupons ficam parados em requested até o e-commerce gerar o
 * código na plataforma externa. Se ninguém acompanha, o solicitante fica
 * sem resposta. Este lembrete faz o atendimento proativo.
 *
 * Schedule sugerido: daily 09:00 — chega no início do expediente.
 */
class CouponsRemindPendingCommand extends Command
{
    protected $signature = 'coupons:remind-pending
                            {--days=3 : Limite de dias em requested}';

    protected $description = 'Lembrete diário de cupons solicitados há mais de N dias sem emissão.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $this->info("Coupons Remind Pending — threshold: {$days} dias");

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
     * Escaneia o tenant atual e dispara os lembretes. Extraído do handle()
     * para ser testável sem o loop de tenants.
     *
     * @return int Número de notificações enviadas
     */
    public function scanTenant(int $days): int
    {
        if (! Schema::hasTable('coupons')) {
            return 0;
        }

        $threshold = now()->subDays($days);

        $stale = Coupon::query()
            ->notDeleted()
            ->forStatus(CouponStatus::REQUESTED)
            ->where(function ($q) use ($threshold) {
                $q->where('requested_at', '<=', $threshold)
                    ->orWhere(function ($q2) use ($threshold) {
                        $q2->whereNull('requested_at')
                            ->where('created_at', '<=', $threshold);
                    });
            })
            ->get();

        if ($stale->isEmpty()) {
            $this->line('  Nada pendente.');

            return 0;
        }

        // Destinatários: todos que podem emitir código
        $recipients = User::all()->filter(
            fn (User $user) => $user->hasPermissionTo(Permission::ISSUE_COUPON_CODE->value)
        )->values();

        if ($recipients->isEmpty()) {
            $this->warn('  Nenhum usuário com ISSUE_COUPON_CODE — '.$stale->count().' cupom(ns) sem destinatário');

            return 0;
        }

        $sent = 0;

        // Envia 1 notificação por cupom (padrão database) — agregamos via UI
        // (sino mostra contador). Manter por cupom permite que o e-commerce
        // clique e abra direto o detalhe.
        foreach ($stale as $coupon) {
            try {
                Notification::send(
                    $recipients,
                    new CouponStatusChangedNotification(
                        coupon: $coupon,
                        fromStatus: CouponStatus::REQUESTED,
                        toStatus: CouponStatus::REQUESTED, // sem transição, apenas reforço
                        actor: null,
                        note: "Aguardando emissão há {$days}+ dias"
                    )
                );
                $sent += $recipients->count();
            } catch (\Throwable $e) {
                $this->warn("  #{$coupon->id} falha ao notificar: {$e->getMessage()}");
            }
        }

        $this->line("  {$stale->count()} cupom(ns) → {$recipients->count()} emissor(es) notificados ({$sent} total)");

        return $sent;
    }
}
