<?php

namespace App\Console\Commands;

use App\Enums\TurnListAttendanceStatus;
use App\Models\Tenant;
use App\Models\TurnListAttendance;
use App\Models\TurnListBreak;
use App\Models\TurnListQueueEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limpa estado da Lista da Vez no fim do dia.
 *
 * Substitui o cleanup "lazy" da v1 (que rodava em cada page load):
 *  - Finaliza atendimentos/pausas que ficaram ATIVOS desde antes da
 *    janela considerada (default 12h). Em loja real, isso só
 *    acontece se a vendedora esqueceu de finalizar antes de fechar.
 *  - Esvazia a fila (turn_list_waiting_queue) — fila não persiste
 *    entre dias, é zerada todo dia 23h.
 *
 * Schedule sugerido: daily 23:00 (após fechamento do PDV) — `routes/console.php`.
 *
 * Operação idempotente e silenciosa (sem evento). Histórico de
 * atendimentos/pausas finalizadas pelo cleanup fica preservado para
 * relatórios; apenas o estado "ativo" é fechado.
 */
class TurnListCleanupCommand extends Command
{
    protected $signature = 'turn-list:cleanup
        {--hours=12 : Idade em horas a partir da qual atendimentos/pausas ainda ativos viram órfãos}';

    protected $description = 'Finaliza atendimentos/pausas órfãos e esvazia a fila no fim do dia.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::SUCCESS;
        }

        $grandTotals = ['attendances' => 0, 'breaks' => 0, 'queue_entries' => 0];

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            try {
                $tenant->run(function () use ($hours, &$grandTotals) {
                    $totals = $this->scanTenant($hours);
                    foreach ($totals as $key => $value) {
                        $grandTotals[$key] += $value;
                    }
                });
            } catch (\Throwable $e) {
                $this->error("  Falha: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Total: %d atendimentos finalizados, %d pausas finalizadas, %d entradas de fila removidas.',
            $grandTotals['attendances'],
            $grandTotals['breaks'],
            $grandTotals['queue_entries'],
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{attendances: int, breaks: int, queue_entries: int}
     */
    public function scanTenant(int $hours): array
    {
        $totals = ['attendances' => 0, 'breaks' => 0, 'queue_entries' => 0];

        if (! Schema::hasTable('turn_list_waiting_queue')) {
            $this->line('  Módulo TurnList não instalado neste tenant — skip.');

            return $totals;
        }

        $threshold = now()->subHours($hours);

        // Atendimentos ativos com started_at < threshold → finaliza
        $orphanAttendances = TurnListAttendance::query()
            ->where('status', TurnListAttendanceStatus::ACTIVE->value)
            ->where('started_at', '<', $threshold)
            ->get();

        foreach ($orphanAttendances as $att) {
            $duration = (int) $att->started_at->diffInSeconds(now());
            $att->update([
                'status' => TurnListAttendanceStatus::FINISHED->value,
                'finished_at' => now(),
                'duration_seconds' => $duration,
                'return_to_queue' => false, // não devolve para fila no cleanup
                'notes' => trim(($att->notes ?? '').' [Finalizado automaticamente no cleanup do dia]'),
            ]);
            $totals['attendances']++;
        }

        if ($totals['attendances'] > 0) {
            $this->line("  Atendimentos órfãos finalizados: {$totals['attendances']}");
        }

        // Pausas ativas com started_at < threshold → finaliza
        $orphanBreaks = TurnListBreak::query()
            ->where('status', TurnListAttendanceStatus::ACTIVE->value)
            ->where('started_at', '<', $threshold)
            ->get();

        foreach ($orphanBreaks as $brk) {
            $duration = (int) $brk->started_at->diffInSeconds(now());
            $brk->update([
                'status' => TurnListAttendanceStatus::FINISHED->value,
                'finished_at' => now(),
                'duration_seconds' => $duration,
            ]);
            $totals['breaks']++;
        }

        if ($totals['breaks'] > 0) {
            $this->line("  Pausas órfãs finalizadas: {$totals['breaks']}");
        }

        // Esvazia a fila — não persiste entre dias
        $totals['queue_entries'] = (int) DB::table('turn_list_waiting_queue')->delete();

        if ($totals['queue_entries'] > 0) {
            $this->line("  Entradas de fila removidas: {$totals['queue_entries']}");
        }

        return $totals;
    }
}
