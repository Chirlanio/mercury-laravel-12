<?php

namespace App\Console\Commands;

use App\Enums\ConsignmentStatus;
use App\Models\Consignment;
use App\Models\Tenant;
use App\Services\ConsignmentTransitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Marca como `overdue` as consignações em aberto (pending ou
 * partially_returned) cuja `expected_return_date` já venceu.
 *
 * Schedule sugerido: daily 06:00 — antes do expediente para que a
 * listagem do dia reflita o estado correto.
 *
 * Idempotente: filtro exclui consignações já em overdue, completed
 * ou cancelled. Rodar 2x no mesmo dia não gera efeito extra.
 *
 * Transição sem actor (passa null pro transition service), registrado
 * no histórico com context {auto: true, command: consignments:mark-overdue}.
 */
class ConsignmentsMarkOverdueCommand extends Command
{
    protected $signature = 'consignments:mark-overdue';

    protected $description = 'Marca consignações com prazo vencido como em atraso (automático, sem actor).';

    public function handle(ConsignmentTransitionService $transitionService): int
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
        $this->info("Total de consignações marcadas como em atraso: {$grandTotal}");

        return self::SUCCESS;
    }

    /**
     * Processa o tenant atual. Extraído do handle() pra ser testável
     * sem precisar do loop de tenants (in-memory SQLite tem 0 tenants).
     */
    public function scanTenant(ConsignmentTransitionService $transitionService): int
    {
        if (! Schema::hasTable('consignments')) {
            return 0;
        }

        $today = now()->toDateString();

        $overdueCandidates = Consignment::query()
            ->notDeleted()
            ->where('expected_return_date', '<', $today)
            ->whereIn('status', [
                ConsignmentStatus::PENDING->value,
                ConsignmentStatus::PARTIALLY_RETURNED->value,
            ])
            ->get();

        if ($overdueCandidates->isEmpty()) {
            $this->line('  Nada vencido.');

            return 0;
        }

        $marked = 0;
        foreach ($overdueCandidates as $consignment) {
            try {
                $transitionService->markOverdue($consignment);
                $marked++;
                $this->line("  #{$consignment->id} ({$consignment->recipient_name}) → overdue (prazo {$consignment->expected_return_date?->format('Y-m-d')})");
            } catch (\Throwable $e) {
                $this->warn("  #{$consignment->id} falhou: {$e->getMessage()}");
            }
        }

        return $marked;
    }
}
