<?php

namespace App\Console\Commands;

use App\Services\DRE\DreMatrixService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Pré-aquece o cache da matriz DRE para acessos típicos.
 *
 * Hoje aquecemos apenas o escopo GENERAL em duas janelas:
 *   - Mês corrente (start = primeiro dia do mês, end = hoje).
 *   - 12 meses móveis (start = primeiro dia do mês há 11 meses, end = hoje).
 *
 * Escopos de rede/loja ficam para expansão futura: aquecer N×lojas triplica
 * o tempo do warm-up sem ganho claro — a UI só chama 1 escopo de cada vez,
 * então vira cache hit na primeira visita do usuário.
 *
 * Agendamento: `routes/console.php` → dailyAt('05:50') com `withoutOverlapping`.
 */
class DreWarmCacheCommand extends Command
{
    protected $signature = 'dre:warm-cache
                            {--scopes=* : escopos a aquecer (default: general)}';

    protected $description = 'Pré-aquece o cache da matriz DRE para mês corrente e 12 meses móveis';

    public function handle(DreMatrixService $matrixService): int
    {
        $today = Carbon::today();

        $ranges = [
            'mês-corrente' => [
                'start_date' => $today->copy()->startOfMonth()->format('Y-m-d'),
                'end_date' => $today->format('Y-m-d'),
            ],
            '12-meses-moveis' => [
                'start_date' => $today->copy()->subMonths(11)->startOfMonth()->format('Y-m-d'),
                'end_date' => $today->format('Y-m-d'),
            ],
        ];

        foreach ($ranges as $label => $range) {
            $this->line("Aquecendo '{$label}' [{$range['start_date']} → {$range['end_date']}]…");
            $started = microtime(true);

            try {
                $matrix = $matrixService->matrix($range + [
                    'scope' => 'general',
                    'include_unclassified' => true,
                    'compare_previous_year' => true,
                ]);

                $elapsed = round((microtime(true) - $started) * 1000);
                $lines = count($matrix['lines'] ?? []);
                $this->info("  OK — {$lines} linhas em {$elapsed}ms.");
            } catch (\Throwable $e) {
                $this->error("  Falhou: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Warm-up concluído.');

        return self::SUCCESS;
    }
}
