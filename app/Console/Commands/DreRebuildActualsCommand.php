<?php

namespace App\Console\Commands;

use App\Services\DRE\OrderPaymentToDreProjector;
use App\Services\DRE\SaleToDreProjector;
use Illuminate\Console\Command;

/**
 * Reprojeta todas as entradas de `dre_actuals` a partir das fontes reais
 * (OrderPayment status=done + Sale).
 *
 * Uso defensivo: roda semanalmente via schedule (domingo 03:00) para
 * reconciliar eventuais divergências causadas por falhas silenciosas dos
 * observers. Também pode ser invocado manualmente após mudanças em massa
 * (import, backfill, mudança de fallback de conta de receita).
 *
 * Opções:
 *   --source=ORDER_PAYMENT | SALE | all (default all).
 *   --force                pula confirmação interativa.
 */
class DreRebuildActualsCommand extends Command
{
    protected $signature = 'dre:rebuild-actuals
                            {--source=all : Fonte a reprojetar: ORDER_PAYMENT, SALE ou all}
                            {--force : Pula confirmação interativa}';

    protected $description = 'Reprojeta dre_actuals a partir de OrderPayment (status=done) e/ou Sale';

    public function handle(
        OrderPaymentToDreProjector $opProjector,
        SaleToDreProjector $saleProjector,
    ): int {
        $source = strtoupper((string) $this->option('source'));
        $validSources = ['ALL', 'ORDER_PAYMENT', 'SALE'];

        if (! in_array($source, $validSources, true)) {
            $this->error("Fonte inválida: {$source}. Use: ORDER_PAYMENT, SALE ou all.");

            return self::INVALID;
        }

        $this->warn('Esta operação APAGA todas as linhas de dre_actuals com a fonte selecionada');
        $this->warn('e reprojeta do zero. Observers podem levar alguns minutos dependendo do volume.');
        $this->newLine();

        if (! $this->option('force')) {
            if (! $this->confirm("Continuar o rebuild da fonte \"{$source}\"?", false)) {
                $this->info('Cancelado.');

                return self::SUCCESS;
            }
        }

        $runOp = $source === 'ALL' || $source === 'ORDER_PAYMENT';
        $runSale = $source === 'ALL' || $source === 'SALE';

        if ($runOp) {
            $this->info('Reprojetando OrderPayment → dre_actuals…');
            $report = $opProjector->rebuild();
            $this->line("  Removidas: {$report->truncated}");
            $this->line("  Projetadas: {$report->projected}");
            $this->line("  Puladas: {$report->skipped}");
            if (! empty($report->skipReasons)) {
                $this->warn('  Amostra de skips (primeiras 10):');
                foreach (array_slice($report->skipReasons, 0, 10) as $reason) {
                    $this->line("    · {$reason}");
                }
            }
            $this->newLine();
        }

        if ($runSale) {
            $this->info('Reprojetando Sale → dre_actuals…');
            $report = $saleProjector->rebuild();
            $this->line("  Removidas: {$report->truncated}");
            $this->line("  Projetadas: {$report->projected}");
            $this->line("  Puladas: {$report->skipped}");
            if (! empty($report->skipReasons)) {
                $this->warn('  Amostra de skips (primeiras 10):');
                foreach (array_slice($report->skipReasons, 0, 10) as $reason) {
                    $this->line("    · {$reason}");
                }
            }
            $this->newLine();
        }

        $this->info('Concluído.');

        return self::SUCCESS;
    }
}
