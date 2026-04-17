<?php

namespace App\Console\Commands;

use App\Enums\ReversalStatus;
use App\Models\Reversal;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Sincroniza estornos executados (status=reversed) com o CIGAM, gravando
 * `synced_to_cigam_at` para idempotência.
 *
 * NOTA DE IMPLEMENTAÇÃO (stub):
 *   A gravação real no CIGAM (PostgreSQL `msl_fmovimentodiario_` ou API
 *   equivalente) exige credenciais de escrita no ERP, que não estão
 *   configuradas neste ambiente. Por enquanto, este command apenas marca
 *   os estornos como "sincronizados" localmente (sem push real). A
 *   contraparte CIGAM deve ser plugada aqui quando a integração de
 *   escrita estiver disponível.
 *
 * Idempotente: scope `pendingCigamSync` só seleciona registros com
 * `synced_to_cigam_at` nulo. Um segundo run no mesmo minuto é no-op.
 *
 * Schedule sugerido: every 15 minutes (same pattern as
 * purchase-orders:cigam-match).
 */
class ReversalsCigamPushCommand extends Command
{
    protected $signature = 'reversals:cigam-push
                            {--dry-run : Lista o que seria sincronizado sem persistir}';

    protected $description = 'Marca estornos executados como sincronizados com o CIGAM (stub — integração real depende de credenciais de escrita no ERP).';

    public function handle(): int
    {
        $this->info('CIGAM Push — varrendo tenants...');

        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');
            return self::SUCCESS;
        }

        $grandTotal = 0;

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use (&$grandTotal) {
                    $grandTotal += $this->scanTenant();
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Total sincronizado: {$grandTotal}");

        return self::SUCCESS;
    }

    /**
     * Processa o tenant atual. Extraído do handle() para permitir testes
     * sem o loop de tenants.
     *
     * @return int Número de estornos sincronizados
     */
    public function scanTenant(): int
    {
        if (! Schema::hasTable('reversals')) {
            $this->warn('  reversals não encontrada — pulando');
            return 0;
        }

        $pending = Reversal::query()
            ->pendingCigamSync()
            ->notDeleted()
            ->limit(500)
            ->get();

        if ($pending->isEmpty()) {
            $this->line('  Nenhum estorno pendente de sync.');
            return 0;
        }

        $this->line("  Pendentes: {$pending->count()}");

        $synced = 0;
        foreach ($pending as $reversal) {
            if ($this->option('dry-run')) {
                $this->line(sprintf(
                    '    [dry-run] reversal=%d NF=%s loja=%s valor=%.2f',
                    $reversal->id,
                    $reversal->invoice_number,
                    $reversal->store_code,
                    (float) $reversal->amount_reversal
                ));
                continue;
            }

            try {
                $this->pushToCigam($reversal);
                $reversal->update(['synced_to_cigam_at' => now()]);
                $synced++;
            } catch (\Throwable $e) {
                Log::warning('Reversal CIGAM push failed', [
                    'reversal_id' => $reversal->id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("    ERRO reversal={$reversal->id}: {$e->getMessage()}");
            }
        }

        return $synced;
    }

    /**
     * Stub da gravação no CIGAM. Será substituído quando a integração real
     * estiver disponível.
     */
    protected function pushToCigam(Reversal $reversal): void
    {
        // No-op por enquanto. O update do synced_to_cigam_at acontece no
        // caller apenas se este método não lançar exceção.
    }
}
