<?php

namespace App\Services\DRE;

use App\Models\ChartOfAccount;
use App\Models\DreActual;
use App\Models\DrePeriodClosing;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Projeta Sale para dre_actuals como receita de venda.
 *
 * Resolução da conta contábil (resposta #17 da arquitetura):
 *   1. Se `sale->store->sale_chart_of_account_id` existe → usa essa.
 *   2. Senão → fallback em `config('dre.default_sale_account_code')`.
 *   3. Se nada resolve → SKIP com log warning (não quebra a criação da Sale).
 *
 * Amount é sempre positivo (receita).
 * cost_center_id é sempre null (Sales não têm CC).
 * store_id = sale->store_id.
 * entry_date = sale->date_sales.
 *
 * A tolerância a "conta não resolvida" é deliberada: a DRE é um serviço
 * secundário ao fluxo operacional de venda. Rebuild (prompt 14 /
 * schedule) corrige o histórico depois que o cadastro da loja for
 * completado.
 */
class SaleToDreProjector
{
    /** Cache do fallback por request — evita N queries em rebuilds grandes. */
    private ?int $fallbackAccountIdCache = null;

    private bool $fallbackResolved = false;

    public function project(Sale $sale): ?DreActual
    {
        return DB::transaction(fn () => $this->projectRaw($sale));
    }

    /**
     * Projeta várias Sales numa única transação. Usado pelo
     * `MovementSyncService::refreshSalesSummary()` após insert/update em
     * bulk via `DB::table()` — chamada raw não dispara observer, então
     * a projeção precisa ser pedida explicitamente.
     *
     * @param  iterable<int>  $saleIds  IDs das Sales inseridas/atualizadas.
     * @return array{projected:int, skipped:int}
     */
    public function projectBatch(iterable $saleIds): array
    {
        $ids = collect($saleIds)->filter()->unique()->values()->all();
        if (empty($ids)) {
            return ['projected' => 0, 'skipped' => 0];
        }

        // Reset dos caches — config pode ter mudado entre batches.
        $this->fallbackAccountIdCache = null;
        $this->fallbackResolved = false;

        $projected = 0;
        $skipped = 0;

        DB::transaction(function () use ($ids, &$projected, &$skipped) {
            Sale::query()
                ->whereIn('id', $ids)
                ->chunkById(500, function ($batch) use (&$projected, &$skipped) {
                    foreach ($batch as $sale) {
                        try {
                            $r = $this->projectRaw($sale);
                            if ($r !== null) {
                                $projected++;
                            } else {
                                $skipped++;
                            }
                        } catch (\Throwable $e) {
                            Log::warning('SaleToDreProjector::projectBatch Sale id='.$sale->id.' falhou', [
                                'error' => $e->getMessage(),
                            ]);
                            $skipped++;
                        }
                    }
                });
        });

        return ['projected' => $projected, 'skipped' => $skipped];
    }

    /**
     * Lógica pura de projeção — sem transaction wrap. Usada tanto pelo
     * `project()` (que abre sua própria transaction) quanto pelo
     * `projectBatch()` (que abre uma transaction única para todo o lote).
     */
    private function projectRaw(Sale $sale): ?DreActual
    {
        $accountId = $this->resolveAccountId($sale);
        if ($accountId === null) {
            Log::warning('SaleToDreProjector: conta de receita não resolvida para Sale id='.$sale->id, [
                'store_id' => $sale->store_id,
                'fallback_code' => config('dre.default_sale_account_code'),
            ]);

            return null;
        }

        $entryDate = $sale->date_sales instanceof \DateTimeInterface
            ? $sale->date_sales->format('Y-m-d')
            : (string) $sale->date_sales;

        $reportedInClosed = $this->isInClosedPeriod($entryDate);

        $attrs = [
            'entry_date' => $entryDate,
            'chart_of_account_id' => $accountId,
            'cost_center_id' => null,
            'store_id' => $sale->store_id,
            'amount' => abs((float) $sale->total_sales),
            'source' => DreActual::SOURCE_SALE,
            'source_type' => Sale::class,
            'source_id' => $sale->id,
            'document' => null,
            'description' => 'Venda #'.$sale->id,
            'reported_in_closed_period' => $reportedInClosed,
        ];

        return DreActual::updateOrCreate(
            ['source_type' => Sale::class, 'source_id' => $sale->id],
            $attrs,
        );
    }

    public function unproject(Sale $sale): void
    {
        DreActual::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->delete();
    }

    public function rebuild(): RebuildReport
    {
        $report = new RebuildReport();

        $report->truncated = DreActual::query()
            ->where('source', DreActual::SOURCE_SALE)
            ->delete();

        // Limpa cache do fallback — pode ter mudado na config entre rebuilds.
        $this->fallbackAccountIdCache = null;
        $this->fallbackResolved = false;

        // Wrap em 1 transação única — 214k INSERTs individuais sem wrap
        // custavam ~7h40min. Com single transaction + projectRaw, queda
        // significativa no número de commits.
        DB::transaction(function () use ($report) {
            Sale::query()->chunkById(500, function ($batch) use ($report) {
                foreach ($batch as $sale) {
                    try {
                        $result = $this->projectRaw($sale);
                        if ($result) {
                            $report->projected++;
                        } else {
                            $report->addSkip("Sale id={$sale->id}: conta de receita não resolvida.");
                        }
                    } catch (\Throwable $e) {
                        $report->addSkip("Sale id={$sale->id}: {$e->getMessage()}");
                    }
                }
            });
        });

        return $report;
    }

    // -----------------------------------------------------------------
    // Helpers privados
    // -----------------------------------------------------------------

    private function resolveAccountId(Sale $sale): ?int
    {
        // 1. Loja configurada.
        if ($sale->store_id) {
            $storeAccountId = DB::table('stores')
                ->where('id', $sale->store_id)
                ->value('sale_chart_of_account_id');

            if ($storeAccountId !== null) {
                return (int) $storeAccountId;
            }
        }

        // 2. Fallback por config.
        return $this->resolveFallbackAccountId();
    }

    private function resolveFallbackAccountId(): ?int
    {
        if ($this->fallbackResolved) {
            return $this->fallbackAccountIdCache;
        }

        $this->fallbackResolved = true;

        $code = config('dre.default_sale_account_code');
        if (! $code) {
            return $this->fallbackAccountIdCache = null;
        }

        $id = ChartOfAccount::query()
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->value('id');

        return $this->fallbackAccountIdCache = ($id !== null ? (int) $id : null);
    }

    private function isInClosedPeriod(string $entryDate): bool
    {
        $lastClosed = DrePeriodClosing::query()
            ->whereNull('reopened_at')
            ->orderByDesc('closed_up_to_date')
            ->value('closed_up_to_date');

        if ($lastClosed === null) {
            return false;
        }

        $lastClosedStr = $lastClosed instanceof \DateTimeInterface
            ? $lastClosed->format('Y-m-d')
            : (string) $lastClosed;

        return $entryDate <= $lastClosedStr;
    }
}
