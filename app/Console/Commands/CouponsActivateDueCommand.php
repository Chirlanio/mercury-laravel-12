<?php

namespace App\Console\Commands;

use App\Enums\CouponStatus;
use App\Models\Coupon;
use App\Models\Tenant;
use App\Services\CouponTransitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Ativa cupons em `issued` cujo `valid_from` já chegou — ou que não tem
 * `valid_from` definido (assumimos que "emitido sem data = vale
 * imediatamente"). Garante que cupons emitidos pelo e-commerce não
 * fiquem presos em `issued` quando a equipe esquece de ativar.
 *
 * Schedule sugerido: daily 05:00 — antes do `coupons:expire-stale`
 * (06:00) para que cupons recém-ativados possam ser expirados no mesmo
 * ciclo se `valid_until` também já passou.
 *
 * Idempotente: filtro só pega `issued`.
 */
class CouponsActivateDueCommand extends Command
{
    protected $signature = 'coupons:activate-due';

    protected $description = 'Ativa automaticamente cupons em issued cuja data de início (valid_from) já chegou.';

    public function handle(CouponTransitionService $transitionService): int
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $grandTotal = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($transitionService, &$grandTotal) {
                    $grandTotal += $this->scanTenant($transitionService);
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total de cupons ativados: {$grandTotal}");

        return self::SUCCESS;
    }

    /**
     * Processa o tenant atual. Extraído do handle() para ser testável
     * sem precisar do loop de tenants (in-memory SQLite tem 0 tenants).
     */
    public function scanTenant(CouponTransitionService $transitionService): int
    {
        if (! Schema::hasTable('coupons')) {
            return 0;
        }

        $today = now()->toDateString();

        $due = Coupon::query()
            ->notDeleted()
            ->where('status', CouponStatus::ISSUED->value)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')
                  ->orWhereDate('valid_from', '<=', $today);
            })
            ->get();

        if ($due->isEmpty()) {
            $this->line('  Nada a ativar.');

            return 0;
        }

        $activated = 0;
        foreach ($due as $coupon) {
            try {
                $transitionService->activateAutomatically($coupon);
                $activated++;
                $vf = $coupon->valid_from?->format('Y-m-d') ?? 'sem data';
                $this->line("  #{$coupon->id} ({$coupon->type?->value}) ativado (valid_from {$vf})");
            } catch (\Throwable $e) {
                $this->warn("  #{$coupon->id} falhou: {$e->getMessage()}");
            }
        }

        return $activated;
    }
}
