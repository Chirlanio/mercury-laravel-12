<?php

namespace App\Services\DRE;

use App\Models\DreManagementLine;
use App\Models\DrePeriodClosing;
use App\Models\DrePeriodClosingSnapshot;
use App\Models\Network;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Serviço de fechamento de períodos da DRE — arquitetura §2.8, playbook
 * prompt #11.
 *
 * Duas operações:
 *   - `close(...)`: cria um `DrePeriodClosing` + snapshots imutáveis cobrindo
 *     Geral/Rede/Loja × meses × linhas. A partir deste ponto o
 *     `DrePeriodSnapshotReader` sobrepõe os valores do snapshot sobre a
 *     matriz live — mesmo que alguém insira `dre_actual` retroativo, a
 *     célula fechada fica estável.
 *   - `reopen(...)`: exige `reason`, computa diff entre snapshot e live,
 *     marca `reopened_at`+`reopened_by`+`reopen_reason`, apaga snapshots e
 *     retorna `ReopenReport` para o controller notificar usuários.
 *
 * Toda a operação de close/reopen roda em transação. Se uma inserção
 * falhar, rollback total — nunca ficamos com fechamento meio-computado.
 */
class DrePeriodClosingService
{
    public function __construct(private readonly DreMatrixService $matrixService)
    {
    }

    /**
     * Fecha o período até `$closedUpToDate` (inclusivo).
     *
     * Regra de negócio:
     *   - `$closedUpToDate` precisa ser posterior a qualquer fechamento ativo.
     *   - Se não houver fechamento anterior, o fechamento cobre do início
     *     dos dados até a data informada.
     *   - O fechamento anterior (se existir) define o `start_date` do novo
     *     fechamento (closed_prev + 1 dia).
     */
    public function close(Carbon $closedUpToDate, User $closedBy, ?string $notes = null): DrePeriodClosing
    {
        $closedUpToStr = $closedUpToDate->copy()->startOfDay()->format('Y-m-d');
        $lastClosed = DrePeriodClosing::lastClosedUpTo();

        if ($lastClosed !== null && $closedUpToStr <= $lastClosed) {
            throw ValidationException::withMessages([
                'closed_up_to_date' => "Data ({$closedUpToStr}) precisa ser posterior ao último fechamento ativo ({$lastClosed}).",
            ]);
        }

        // Intervalo a ser "fotografado": do dia seguinte ao último fechamento
        // (ou 2000-01-01 se nunca houve fechamento) até o closed_up_to_date.
        $from = $lastClosed !== null
            ? Carbon::parse($lastClosed)->addDay()->startOfDay()
            : Carbon::create(2000, 1, 1)->startOfDay();
        $to = Carbon::parse($closedUpToStr)->endOfDay();

        return DB::transaction(function () use ($closedUpToStr, $closedBy, $notes, $from, $to) {
            $closing = DrePeriodClosing::create([
                'closed_up_to_date' => $closedUpToStr,
                'closed_by_user_id' => $closedBy->id,
                'closed_at' => now(),
                'notes' => $notes,
            ]);

            $report = $this->generateSnapshots($closing, $from, $to);

            // Persiste marcador para telemetria / log.
            $closing->forceFill([
                'notes' => $notes ?? sprintf(
                    '%d snapshots gerados (%d Geral + %d Rede + %d Loja × %d meses).',
                    $report->total(),
                    $report->generalSnapshots,
                    $report->networkSnapshots,
                    $report->storeSnapshots,
                    $report->yearMonths,
                ),
            ])->save();

            return $closing->fresh();
        });
    }

    /**
     * Reabre um fechamento existente. Computa diff contra a matriz live atual,
     * marca reopened_at/_by/_reason e apaga snapshots.
     */
    public function reopen(DrePeriodClosing $closing, User $reopenedBy, string $reason): ReopenReport
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Justificativa é obrigatória para reabrir um fechamento.',
            ]);
        }

        if ($closing->reopened_at !== null) {
            throw ValidationException::withMessages([
                'id' => 'Este fechamento já foi reaberto.',
            ]);
        }

        $report = $this->computeReopenDiffs($closing);

        DB::transaction(function () use ($closing, $reopenedBy, $reason, $report) {
            $report->snapshotsDeleted = DrePeriodClosingSnapshot::query()
                ->where('dre_period_closing_id', $closing->id)
                ->delete();

            $closing->update([
                'reopened_at' => now(),
                'reopened_by_user_id' => $reopenedBy->id,
                'reopen_reason' => $reason,
            ]);
        });

        return $report;
    }

    /**
     * Preview do diff sem de fato reabrir — usado pela UI para mostrar o
     * que vai mudar antes da confirmação.
     */
    public function previewReopenDiffs(DrePeriodClosing $closing): ReopenReport
    {
        if ($closing->reopened_at !== null) {
            // Sem diff: já foi reaberto.
            return new ReopenReport();
        }

        return $this->computeReopenDiffs($closing);
    }

    // ---------------------------------------------------------------------
    // Snapshot generation
    // ---------------------------------------------------------------------

    private function generateSnapshots(DrePeriodClosing $closing, Carbon $from, Carbon $to): ClosingReport
    {
        $report = new ClosingReport();

        $yearMonths = $this->yearMonthsBetween($from, $to);
        $report->yearMonths = count($yearMonths);
        if ($yearMonths === []) {
            return $report;
        }

        $baseFilter = [
            'start_date' => $from->format('Y-m-d'),
            'end_date' => $to->format('Y-m-d'),
            'include_unclassified' => true,
            'compare_previous_year' => false,
            'budget_version' => null,
            'scope' => 'general',
            'store_ids' => [],
            'network_ids' => [],
        ];

        // GERAL — 1 matriz para todo o universo.
        $this->persistScope(
            closing: $closing,
            scope: DrePeriodClosingSnapshot::SCOPE_GENERAL,
            scopeId: null,
            filter: $baseFilter,
            counter: function () use ($report) {
                $report->generalSnapshots++;
            },
        );

        // REDES — 1 matriz por rede ativa.
        $networkIds = Network::query()
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('networks', 'deleted_at'),
                fn ($q) => $q->whereNull('deleted_at'),
            )
            ->pluck('id')
            ->all();

        foreach ($networkIds as $networkId) {
            $this->persistScope(
                closing: $closing,
                scope: DrePeriodClosingSnapshot::SCOPE_NETWORK,
                scopeId: (int) $networkId,
                filter: array_merge($baseFilter, [
                    'scope' => 'network',
                    'network_ids' => [(int) $networkId],
                ]),
                counter: function () use ($report) {
                    $report->networkSnapshots++;
                },
            );
        }

        // LOJAS — 1 matriz por loja.
        $storeIds = Store::query()
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('stores', 'deleted_at'),
                fn ($q) => $q->whereNull('deleted_at'),
            )
            ->pluck('id')
            ->all();

        foreach ($storeIds as $storeId) {
            $this->persistScope(
                closing: $closing,
                scope: DrePeriodClosingSnapshot::SCOPE_STORE,
                scopeId: (int) $storeId,
                filter: array_merge($baseFilter, [
                    'scope' => 'store',
                    'store_ids' => [(int) $storeId],
                ]),
                counter: function () use ($report) {
                    $report->storeSnapshots++;
                },
            );
        }

        return $report;
    }

    /**
     * Computa matriz live do escopo e persiste snapshots por (year_month × line).
     */
    private function persistScope(
        DrePeriodClosing $closing,
        string $scope,
        ?int $scopeId,
        array $filter,
        \Closure $counter,
    ): void {
        $matrix = $this->matrixService->matrix($filter);

        $rowsToInsert = [];
        $now = now();

        foreach ($matrix['lines'] as $line) {
            foreach ($line['months'] ?? [] as $ym => $cell) {
                // Zeros não valem snapshot.
                if (($cell['actual'] ?? 0.0) == 0.0 && ($cell['budget'] ?? 0.0) == 0.0) {
                    continue;
                }

                $rowsToInsert[] = [
                    'dre_period_closing_id' => $closing->id,
                    'scope' => $scope,
                    'scope_id' => $scopeId,
                    'dre_management_line_id' => (int) $line['id'],
                    'year_month' => (string) $ym,
                    'actual_amount' => round((float) ($cell['actual'] ?? 0.0), 2),
                    'budget_amount' => round((float) ($cell['budget'] ?? 0.0), 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $counter();

                if (count($rowsToInsert) >= 500) {
                    DrePeriodClosingSnapshot::insert($rowsToInsert);
                    $rowsToInsert = [];
                }
            }
        }

        if ($rowsToInsert !== []) {
            DrePeriodClosingSnapshot::insert($rowsToInsert);
        }
    }

    // ---------------------------------------------------------------------
    // Reopen diff
    // ---------------------------------------------------------------------

    private function computeReopenDiffs(DrePeriodClosing $closing): ReopenReport
    {
        $report = new ReopenReport();

        $snapshots = DrePeriodClosingSnapshot::query()
            ->where('dre_period_closing_id', $closing->id)
            ->get();

        if ($snapshots->isEmpty()) {
            return $report;
        }

        $managementLines = DreManagementLine::query()
            ->whereIn('id', $snapshots->pluck('dre_management_line_id')->unique())
            ->get()
            ->keyBy('id');

        // Para comparar contra live, computamos a matriz live no mesmo
        // intervalo do fechamento e no mesmo escopo de cada snapshot.
        // Cache por escopo para não recomputar N vezes a mesma query.
        $liveCache = [];

        foreach ($snapshots as $snap) {
            $scope = strtolower((string) $snap->scope);
            $scopeKey = $scope.':'.($snap->scope_id ?? 'null');

            if (! isset($liveCache[$scopeKey])) {
                $liveCache[$scopeKey] = $this->computeLiveForSnapshot($closing, $snap);
            }

            $live = $liveCache[$scopeKey];
            $ym = (string) $snap->year_month;
            $lineId = (int) $snap->dre_management_line_id;

            $currentActual = (float) ($live[$ym][$lineId]['actual'] ?? 0.0);
            $snapshotActual = (float) $snap->actual_amount;
            $delta = round($currentActual - $snapshotActual, 2);

            if (abs($delta) < 0.01) {
                continue;
            }

            $line = $managementLines[$lineId] ?? null;
            $report->diffs[] = [
                'scope' => $snap->scope,
                'scope_id' => $snap->scope_id,
                'line_id' => $lineId,
                'line_code' => $line?->code,
                'line_name' => $line?->level_1,
                'year_month' => $ym,
                'snapshot_actual' => $snapshotActual,
                'current_actual' => $currentActual,
                'delta' => $delta,
            ];
        }

        return $report;
    }

    /**
     * Computa matriz live no mesmo escopo/intervalo de um snapshot — indexada
     * por year_month → line_id → {actual, budget}.
     *
     * @return array<string, array<int, array<string, float>>>
     */
    private function computeLiveForSnapshot(DrePeriodClosing $closing, DrePeriodClosingSnapshot $snap): array
    {
        // Intervalo: do dia seguinte ao fechamento imediatamente anterior
        // ATIVO (se houver) ao closed_up_to_date do fechamento atual.
        $priorClosed = DrePeriodClosing::query()
            ->whereNull('reopened_at')
            ->where('id', '!=', $closing->id)
            ->where('closed_up_to_date', '<', $closing->closed_up_to_date)
            ->orderByDesc('closed_up_to_date')
            ->value('closed_up_to_date');

        $from = $priorClosed !== null
            ? Carbon::parse((string) $priorClosed)->addDay()->startOfDay()
            : Carbon::create(2000, 1, 1)->startOfDay();
        $to = Carbon::parse((string) $closing->closed_up_to_date)->endOfDay();

        $filter = [
            'start_date' => $from->format('Y-m-d'),
            'end_date' => $to->format('Y-m-d'),
            'scope' => match ($snap->scope) {
                DrePeriodClosingSnapshot::SCOPE_STORE => 'store',
                DrePeriodClosingSnapshot::SCOPE_NETWORK => 'network',
                default => 'general',
            },
            'store_ids' => $snap->scope === DrePeriodClosingSnapshot::SCOPE_STORE && $snap->scope_id
                ? [(int) $snap->scope_id]
                : [],
            'network_ids' => $snap->scope === DrePeriodClosingSnapshot::SCOPE_NETWORK && $snap->scope_id
                ? [(int) $snap->scope_id]
                : [],
            'budget_version' => null,
            'include_unclassified' => true,
            'compare_previous_year' => false,
        ];

        // Computa matriz SEM o overlay (do contrário a matriz leria o snapshot
        // que estamos tentando comparar). A forma simples: chamar o matrixService
        // bypassando o reader. Aqui, temporariamente trocamos o reader no
        // container via `app()->instance()` (opção B do trade-off entre expor
        // parâmetro do service ou usar container).
        $original = app(Contracts\ClosedPeriodReader::class);
        app()->instance(Contracts\ClosedPeriodReader::class, new NullClosedPeriodReader());
        try {
            $matrix = app(DreMatrixService::class)->matrix($filter);
        } finally {
            app()->instance(Contracts\ClosedPeriodReader::class, $original);
        }

        $indexed = [];
        foreach ($matrix['lines'] as $line) {
            foreach ($line['months'] ?? [] as $ym => $cell) {
                $indexed[(string) $ym][(int) $line['id']] = [
                    'actual' => (float) ($cell['actual'] ?? 0.0),
                    'budget' => (float) ($cell['budget'] ?? 0.0),
                ];
            }
        }

        return $indexed;
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @return array<int,string>  'YYYY-MM'
     */
    private function yearMonthsBetween(Carbon $from, Carbon $to): array
    {
        $result = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth();
        while ($cursor <= $end) {
            $result[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $result;
    }
}
