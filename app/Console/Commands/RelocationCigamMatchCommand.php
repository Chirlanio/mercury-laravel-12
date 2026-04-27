<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\RelocationCigamMatcherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Roda o matcher CIGAM em todos os remanejos in_transit pendentes de
 * todos os tenants.
 *
 * O matcher casa em DUAS PONTAS:
 *   1. ORIGEM: movement_code=5 + entry_exit='S' + invoice_number — saída
 *      registrada no CIGAM. Marca cigam_dispatched_at e agrega
 *      dispatched_quantity (aderência da origem).
 *   2. DESTINO: movement_code=5 + entry_exit='E' + invoice_number — entrada
 *      registrada no CIGAM. Marca cigam_received_at e agrega
 *      received_quantity.
 *
 * Schedule: every 15 min, depois do movements:sync (everyFiveMinutes
 * + dailyAt 06:00). NÃO transita status — recebimento físico continua
 * sendo manual pela loja destino.
 */
class RelocationCigamMatchCommand extends Command
{
    protected $signature = 'relocations:cigam-match';

    protected $description = 'Casa NF de remanejos in_transit em 2 pontas (saída origem code=5+S e entrada destino code=5+E) e agrega dispatched/received_quantity por barcode.';

    public function handle(RelocationCigamMatcherService $matcher): int
    {
        $this->info('Relocations CIGAM Match — varrendo tenants...');

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');
            return self::SUCCESS;
        }

        $totalChecked = 0;
        $totalMatched = 0;
        $totalItems = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($matcher, &$totalChecked, &$totalMatched, &$totalItems) {
                    if (! Schema::hasTable('relocations')) {
                        $this->warn('  relocations não encontrada — pulando');
                        return;
                    }

                    $result = $matcher->matchAllPending();
                    $totalChecked += $result['relocations_checked'];
                    $totalMatched += $result['dispatched_matched'] + $result['received_matched'];
                    $totalItems += $result['total_items_dispatched'] + $result['total_items_received'];

                    $this->line(sprintf(
                        '  Pendentes: %d · Saídas casadas: %d (%d itens) · Entradas casadas: %d (%d itens)',
                        $result['relocations_checked'],
                        $result['dispatched_matched'],
                        $result['total_items_dispatched'],
                        $result['received_matched'],
                        $result['total_items_received'],
                    ));
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total: {$totalChecked} pendentes verificados, {$totalMatched} pontas casadas, {$totalItems} items.");

        return self::SUCCESS;
    }
}
