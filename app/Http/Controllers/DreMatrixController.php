<?php

namespace App\Http\Controllers;

use App\Exports\DRE\DreMatrixExport;
use App\Http\Requests\DRE\DreMatrixRequest;
use App\Models\DreBudget;
use App\Models\DreManagementLine;
use App\Models\DrePeriodClosing;
use App\Models\Network;
use App\Models\Store;
use App\Services\DRE\DreMatrixService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Camada HTTP da matriz DRE.
 *
 * Este controller é magro — todo o trabalho pesado está em
 * `App\Services\DRE\DreMatrixService`. Aqui só validamos o filtro via
 * `DreMatrixRequest`, montamos os metadados de referência (stores, redes,
 * versões de budget, fechamentos recentes) e devolvemos via Inertia.
 *
 * UI completa da matriz vem no prompt 9 do playbook. Neste prompt, o
 * componente React (`Pages/DRE/Matrix.jsx`) é apenas stub de wiring.
 */
class DreMatrixController extends Controller
{
    public function __construct(private readonly DreMatrixService $service)
    {
    }

    public function show(DreMatrixRequest $request): Response
    {
        $filter = $request->normalized();

        $matrix = $this->service->matrix($filter);
        $kpis = $this->service->kpis($filter);

        return Inertia::render('DRE/Matrix', [
            'filters' => $filter,
            'matrix' => $matrix,
            'kpis' => $kpis,
            'availableStores' => $this->availableStores(),
            'availableNetworks' => $this->availableNetworks(),
            'availableBudgetVersions' => $this->availableBudgetVersions(),
            'closedPeriods' => $this->recentClosedPeriods(),
        ]);
    }

    /**
     * Exporta a matriz (mesmo filtro da tela) como XLSX multi-sheet.
     * Gated pela permission `EXPORT_DRE` aplicada na rota.
     */
    public function exportXlsx(DreMatrixRequest $request): BinaryFileResponse
    {
        $filter = $request->normalized();
        $user = $request->user();

        $filename = sprintf(
            'dre-matriz-%s-a-%s.xlsx',
            (string) ($filter['start_date'] ?? 'periodo'),
            (string) ($filter['end_date'] ?? 'periodo'),
        );

        return DreMatrixExport::fromFilter($filter, $user?->name)->download($filename);
    }

    /**
     * Exporta a matriz como PDF A4 landscape via dompdf.
     */
    public function exportPdf(DreMatrixRequest $request): HttpResponse
    {
        $filter = $request->normalized();
        $user = $request->user();

        $matrix = $this->service->matrix($filter);
        $kpis = $this->service->kpis($filter);

        $yearMonths = $this->yearMonthsFromFilter($filter);

        $pdf = Pdf::loadView('pdf.dre-matrix', [
            'filter' => $filter,
            'matrix' => $matrix,
            'kpis' => $kpis,
            'yearMonths' => $yearMonths,
            'generatedByName' => $user?->name,
        ])->setPaper('a4', 'landscape');

        $filename = sprintf(
            'dre-matriz-%s-a-%s.pdf',
            (string) ($filter['start_date'] ?? 'periodo'),
            (string) ($filter['end_date'] ?? 'periodo'),
        );

        return $pdf->download($filename);
    }

    /** @return array<int,string> */
    private function yearMonthsFromFilter(array $filter): array
    {
        $from = (string) ($filter['start_date'] ?? '');
        $to = (string) ($filter['end_date'] ?? '');
        if ($from === '' || $to === '') {
            return [];
        }

        $out = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->endOfMonth();
        while ($cursor <= $end) {
            $out[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $out;
    }

    /**
     * Drill-through de uma linha específica — retorna as contas contribuintes.
     * Endpoint JSON (consumido pelo modal da matriz no prompt 9).
     */
    public function drill(DreMatrixRequest $request, DreManagementLine $line): JsonResponse
    {
        $filter = $request->normalized();
        $contributors = $this->service->drill($line->id, $filter);

        return response()->json([
            'line' => [
                'id' => $line->id,
                'code' => $line->code,
                'level_1' => $line->level_1,
                'is_subtotal' => (bool) $line->is_subtotal,
                'nature' => $line->nature,
            ],
            'contributors' => $contributors,
            'filter' => $filter,
        ]);
    }

    // -----------------------------------------------------------------
    // Metadados de referência
    // -----------------------------------------------------------------

    private function availableStores(): array
    {
        return Store::query()
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'network_id'])
            ->map(fn (Store $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'network_id' => $s->network_id,
            ])
            ->values()
            ->toArray();
    }

    private function availableNetworks(): array
    {
        return Network::query()
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (Network $n) => [
                'id' => $n->id,
                'name' => $n->nome,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Versões de budget distintas em uso (ex: 'v1', 'action_plan_v1',
     * 'revisado_jun'). Útil para popular o select da matriz.
     *
     * @return array<int,string>
     */
    private function availableBudgetVersions(): array
    {
        return DreBudget::query()
            ->distinct()
            ->orderBy('budget_version')
            ->pluck('budget_version')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Últimos 12 fechamentos — usado pela UI para exibir banner em meses
     * fechados e para o menu de reabertura (prompt 11).
     */
    private function recentClosedPeriods(): array
    {
        return DrePeriodClosing::query()
            ->orderByDesc('closed_up_to_date')
            ->limit(12)
            ->get(['id', 'closed_up_to_date', 'closed_at', 'reopened_at'])
            ->map(fn (DrePeriodClosing $p) => [
                'id' => $p->id,
                'closed_up_to_date' => $p->closed_up_to_date?->format('Y-m-d'),
                'closed_at' => $p->closed_at?->toIso8601String(),
                'reopened_at' => $p->reopened_at?->toIso8601String(),
                'is_active' => $p->isActive(),
            ])
            ->values()
            ->toArray();
    }
}
