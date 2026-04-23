<?php

namespace App\Console\Commands;

use App\Models\ConsignmentReturn;
use App\Models\Movement;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Reconcilia `consignment_returns` com `movements` do CIGAM.
 *
 * Quando o operador lança um retorno manualmente, informa o número
 * da NF de retorno + loja + data. O movement correspondente
 * (movement_code=21) pode ainda não estar sincronizado — este
 * command varre periodicamente e vincula `movement_id` + grava
 * `reconciled_at` quando o movement aparece.
 *
 * Idempotente: filtra `whereNull('movement_id')` — retornos já
 * conciliados são ignorados. Pode rodar várias vezes ao dia sem
 * efeito colateral.
 *
 * Schedule sugerido: every 15min — mesmo ritmo do movements:sync.
 */
class ConsignmentsCigamMatchCommand extends Command
{
    protected $signature = 'consignments:cigam-match
                            {--dry-run : Lista o que seria reconciliado sem persistir}';

    protected $description = 'Reconcilia retornos de consignação com movements CIGAM (code=21).';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info('CIGAM Match (consignments) — varrendo tenants'.($dry ? ' [DRY-RUN]' : '').'…');

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $grandTotal = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use (&$grandTotal, $dry) {
                    $grandTotal += $this->scanTenant($dry);
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total reconciliado: {$grandTotal}");

        return self::SUCCESS;
    }

    /**
     * Escaneia o tenant atual. Extraído do handle() para testabilidade
     * sem precisar do loop de tenants.
     *
     * @return int Número de retornos reconciliados
     */
    public function scanTenant(bool $dry = false): int
    {
        if (! Schema::hasTable('consignment_returns') || ! Schema::hasTable('movements')) {
            return 0;
        }

        // Retornos com NF informada mas sem movement_id ainda resolvido
        $pending = ConsignmentReturn::query()
            ->whereNull('movement_id')
            ->whereNotNull('return_invoice_number')
            ->whereNotNull('return_store_code')
            ->get();

        if ($pending->isEmpty()) {
            $this->line('  Nada a reconciliar.');

            return 0;
        }

        $matched = 0;
        foreach ($pending as $return) {
            $movement = Movement::query()
                ->where('movement_code', 21)
                ->where('store_code', $return->return_store_code)
                ->where('invoice_number', $return->return_invoice_number)
                ->where('movement_date', $return->return_date?->format('Y-m-d'))
                ->first();

            if (! $movement) {
                continue;
            }

            if ($dry) {
                $this->line("  [DRY] Return #{$return->id} → movement #{$movement->id} (NF {$return->return_invoice_number})");
                $matched++;
                continue;
            }

            try {
                $return->update([
                    'movement_id' => $movement->id,
                    'reconciled_at' => now(),
                ]);
                $matched++;
                $this->line("  Return #{$return->id} ← movement #{$movement->id} (NF {$return->return_invoice_number})");
            } catch (\Throwable $e) {
                $this->warn("  Return #{$return->id} falhou: {$e->getMessage()}");
            }
        }

        return $matched;
    }
}
