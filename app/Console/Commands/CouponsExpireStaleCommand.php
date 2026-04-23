<?php

namespace App\Console\Commands;

use App\Enums\CouponStatus;
use App\Models\Coupon;
use App\Models\Tenant;
use App\Services\CouponTransitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Marca como `expired` os cupons com `valid_until < hoje` que ainda
 * estão em estados ativos (issued/active — não mexe em draft/requested
 * porque esses ainda nem foram emitidos).
 *
 * Schedule sugerido: daily 06:00 — antes do expediente, para que a
 * listagem do dia já reflita o estado correto.
 *
 * Idempotente: se rodado 2x no mesmo dia, nada acontece na 2ª execução
 * (filtro exclui cupons já expirados).
 */
class CouponsExpireStaleCommand extends Command
{
    protected $signature = 'coupons:expire-stale';

    protected $description = 'Marca cupons com validade vencida como expirados (automático, sem actor).';

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
        $this->info("Total de cupons expirados: {$grandTotal}");

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

        $stale = Coupon::query()
            ->notDeleted()
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', $today)
            ->whereIn('status', [
                CouponStatus::ISSUED->value,
                CouponStatus::ACTIVE->value,
            ])
            ->get();

        if ($stale->isEmpty()) {
            $this->line('  Nada vencido.');

            return 0;
        }

        $expired = 0;
        foreach ($stale as $coupon) {
            try {
                $transitionService->expire($coupon);
                $expired++;
                $this->line("  #{$coupon->id} ({$coupon->type?->value}) expirado (valid_until {$coupon->valid_until?->format('Y-m-d')})");
            } catch (\Throwable $e) {
                $this->warn("  #{$coupon->id} falhou: {$e->getMessage()}");
            }
        }

        return $expired;
    }
}
