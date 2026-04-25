<?php

namespace App\Console\Commands;

use App\Enums\TravelExpenseStatus;
use App\Models\Tenant;
use App\Models\TravelExpense;
use App\Models\TravelExpenseStatusHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cancela verbas em DRAFT que ficaram >30 dias sem submissão. Limpa lixo
 * de rascunhos abandonados que poluem listagens e estatísticas.
 *
 * Schedule sugerido: daily 02:00 — fora do horário comercial, sem
 * impacto de carga no banco em horário de pico.
 *
 * Idempotente: registros já cancelados são excluídos do filtro pelo
 * status check, então rodar 2x não causa efeito.
 *
 * Não dispara o evento TravelExpenseStatusChanged — é uma operação
 * silenciosa de housekeeping. O history é gravado para auditoria.
 */
class TravelExpensesAutoCancelStaleCommand extends Command
{
    protected $signature = 'travel-expenses:auto-cancel-stale {--days=30 : Idade em dias de drafts a cancelar}';

    protected $description = 'Cancela verbas em rascunho abandonadas (>30 dias por padrão).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
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
        $this->info("Total de drafts cancelados: {$grandTotal}");

        return self::SUCCESS;
    }

    public function scanTenant(int $days): int
    {
        if (! Schema::hasTable('travel_expenses')) {
            return 0;
        }

        $threshold = now()->subDays($days);

        $stale = TravelExpense::query()
            ->notDeleted()
            ->forStatus(TravelExpenseStatus::DRAFT)
            ->where('created_at', '<', $threshold)
            ->get();

        if ($stale->isEmpty()) {
            $this->line('  Nenhum draft expirado.');

            return 0;
        }

        $cancelled = 0;
        foreach ($stale as $te) {
            try {
                DB::transaction(function () use ($te, $days) {
                    $te->update([
                        'status' => TravelExpenseStatus::CANCELLED->value,
                        'cancelled_at' => now(),
                        'cancelled_reason' => "Cancelamento automático: rascunho abandonado há mais de {$days} dias.",
                    ]);

                    TravelExpenseStatusHistory::create([
                        'travel_expense_id' => $te->id,
                        'kind' => TravelExpenseStatusHistory::KIND_EXPENSE,
                        'from_status' => TravelExpenseStatus::DRAFT->value,
                        'to_status' => TravelExpenseStatus::CANCELLED->value,
                        'changed_by_user_id' => null,
                        'note' => "Cancelamento automático (rascunho abandonado há >{$days} dias)",
                        'created_at' => now(),
                    ]);
                });

                $cancelled++;
                $this->line("  #{$te->id} cancelado (criado em {$te->created_at?->format('Y-m-d')})");
            } catch (\Throwable $e) {
                $this->warn("  #{$te->id} falhou: {$e->getMessage()}");
            }
        }

        return $cancelled;
    }
}
