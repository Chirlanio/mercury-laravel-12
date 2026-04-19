<?php

namespace App\Http\Controllers;

use App\Jobs\SyncMovementsJob;
use App\Models\Movement;
use App\Models\MovementSyncLog;
use App\Models\MovementType;
use App\Models\Store;
use App\Services\CigamSyncService;
use App\Services\MovementInvoiceService;
use App\Services\MovementListExportService;
use App\Services\MovementSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MovementController extends Controller
{
    /**
     * Aplica os filtros de listagem (index + exports) a uma query de Movement.
     * Centraliza a lógica para garantir que o export reflita exatamente a listagem.
     *
     * @return array{query: \Illuminate\Database\Eloquent\Builder, filters: array}
     */
    protected function buildFilteredQuery(Request $request): array
    {
        $dateStart = $request->get('date_start', now()->toDateString());
        $dateEnd = $request->get('date_end', now()->toDateString());
        $storeCode = $request->get('store_code');
        $movementCode = $request->get('movement_code');
        $entryExit = $request->get('entry_exit');
        $cpfConsultant = $request->get('cpf_consultant');
        $cpfCustomer = $request->get('cpf_customer');
        $syncStatus = $request->get('sync_status');
        $search = $request->get('search');

        $query = Movement::query()
            ->with('movementType')
            ->forDateRange($dateStart, $dateEnd)
            ->orderByDesc('movement_date')
            ->orderByDesc('movement_time');

        if ($storeCode) {
            $query->forStore($storeCode);
        }

        if ($movementCode) {
            $query->forMovementCode((int) $movementCode);
        }

        if (in_array($entryExit, ['E', 'S'], true)) {
            $query->where('entry_exit', $entryExit);
        }

        if ($cpfConsultant) {
            $query->where('cpf_consultant', 'like', preg_replace('/\D/', '', $cpfConsultant).'%');
        }

        if ($cpfCustomer) {
            $query->where('cpf_customer', 'like', preg_replace('/\D/', '', $cpfCustomer).'%');
        }

        if ($syncStatus === 'synced') {
            $query->whereNotNull('synced_at');
        } elseif ($syncStatus === 'pending') {
            $query->whereNull('synced_at');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('barcode', 'like', "%{$search}%")
                    ->orWhere('ref_size', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhere('cpf_consultant', 'like', "%{$search}%")
                    ->orWhere('cpf_customer', 'like', "%{$search}%");
            });
        }

        return [
            'query' => $query,
            'filters' => [
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'store_code' => $storeCode,
                'movement_code' => $movementCode,
                'entry_exit' => $entryExit,
                'cpf_consultant' => $cpfConsultant,
                'cpf_customer' => $cpfCustomer,
                'sync_status' => $syncStatus,
                'search' => $search,
            ],
        ];
    }

    public function index(Request $request)
    {
        ['query' => $query, 'filters' => $filters] = $this->buildFilteredQuery($request);

        $movements = $query->paginate(50)->through(fn ($m) => [
            'id' => $m->id,
            'movement_date' => $m->movement_date->format('d/m/Y'),
            'movement_time' => $m->movement_time ? substr($m->movement_time, 0, 8) : '-',
            'store_code' => $m->store_code,
            'invoice_number' => $m->invoice_number,
            'movement_code' => $m->movement_code,
            'movement_type' => $m->movementType?->description ?? $m->movement_code,
            'cpf_consultant' => $m->cpf_consultant,
            'ref_size' => $m->ref_size,
            'barcode' => $m->barcode,
            'quantity' => (float) $m->quantity,
            'realized_value' => (float) $m->realized_value,
            'net_value' => (float) $m->net_value,
            'entry_exit' => $m->entry_exit,
            'cpf_customer' => $m->cpf_customer,
            'sale_price' => (float) $m->sale_price,
            'cost_price' => (float) $m->cost_price,
            'discount_value' => (float) $m->discount_value,
            'net_quantity' => (float) $m->net_quantity,
            'synced_at' => $m->synced_at,
        ]);

        $stores = Store::active()->orderedByNetwork()->get()
            ->map(fn ($s) => ['code' => $s->code, 'name' => $s->display_name]);

        $movementTypes = MovementType::orderBy('code')->get()
            ->map(fn ($t) => ['code' => $t->code, 'description' => $t->description]);

        $cigamService = app(CigamSyncService::class);
        $cigamAvailable = $cigamService->isAvailable();

        return Inertia::render('Movements/Index', [
            'movements' => $movements,
            'stores' => $stores,
            'movementTypes' => $movementTypes,
            'filters' => $filters,
            'cigamAvailable' => $cigamAvailable,
            'cigamUnavailableReason' => $cigamAvailable ? null : $cigamService->getUnavailableReason(),
        ]);
    }

    public function statistics(Request $request)
    {
        $refDate = $request->get('date', now()->toDateString());
        $ref = Carbon::parse($refDate);
        $yesterday = $ref->copy()->subDay()->toDateString();
        $lastWeek = $ref->copy()->subWeek()->toDateString();
        $today = $ref->toDateString();

        // Única query agregando os 3 períodos via CASE WHEN.
        // Usa idx_mov_sales_agg (movement_date + movement_code + store_code).
        $row = DB::table('movements')
            ->selectRaw("
                SUM(CASE WHEN movement_date = ? AND movement_code = 2 THEN realized_value ELSE 0 END) as today_sales,
                SUM(CASE WHEN movement_date = ? AND movement_code = 6 AND entry_exit = 'E' THEN realized_value ELSE 0 END) as today_returns,
                SUM(CASE WHEN movement_date = ? AND movement_code = 2 THEN quantity ELSE 0 END) as today_items,
                SUM(CASE WHEN movement_date = ? THEN 1 ELSE 0 END) as today_total,
                COUNT(DISTINCT CASE WHEN movement_date = ? THEN store_code END) as today_active_stores,
                SUM(CASE WHEN movement_date = ? AND movement_code = 2 THEN realized_value ELSE 0 END) as yesterday_sales,
                SUM(CASE WHEN movement_date = ? AND movement_code = 6 AND entry_exit = 'E' THEN realized_value ELSE 0 END) as yesterday_returns,
                SUM(CASE WHEN movement_date = ? AND movement_code = 2 THEN realized_value ELSE 0 END) as lastweek_sales,
                SUM(CASE WHEN movement_date = ? AND movement_code = 6 AND entry_exit = 'E' THEN realized_value ELSE 0 END) as lastweek_returns
            ", [$today, $today, $today, $today, $today, $yesterday, $yesterday, $lastWeek, $lastWeek])
            ->whereIn('movement_date', [$today, $yesterday, $lastWeek])
            ->first();

        $todayNet = (float) ($row->today_sales ?? 0) - (float) ($row->today_returns ?? 0);
        $yesterdayNet = (float) ($row->yesterday_sales ?? 0) - (float) ($row->yesterday_returns ?? 0);
        $lastWeekNet = (float) ($row->lastweek_sales ?? 0) - (float) ($row->lastweek_returns ?? 0);

        $variationYesterday = $yesterdayNet > 0
            ? round((($todayNet - $yesterdayNet) / $yesterdayNet) * 100, 1)
            : null;
        $variationWeek = $lastWeekNet > 0
            ? round((($todayNet - $lastWeekNet) / $lastWeekNet) * 100, 1)
            : null;

        return response()->json([
            'today_net' => round($todayNet, 2),
            'yesterday_net' => round($yesterdayNet, 2),
            'variation_yesterday' => $variationYesterday,
            'last_week_net' => round($lastWeekNet, 2),
            'variation_week' => $variationWeek,
            'items_sold' => (int) ($row->today_items ?? 0),
            'active_stores' => (int) ($row->today_active_stores ?? 0),
            'total_movements' => (int) ($row->today_total ?? 0),
            'last_sync' => Movement::max('synced_at'),
        ]);
    }

    // =============================================
    // INVOICE (nota fiscal) — lista itens + export XLSX/PDF
    // =============================================

    public function invoice(string $storeCode, string $invoiceNumber)
    {
        $data = app(MovementInvoiceService::class)->find($storeCode, $invoiceNumber);

        if (! $data) {
            return response()->json(['message' => 'Nota fiscal não encontrada.'], 404);
        }

        return response()->json([
            'header' => $data['header'],
            'items' => $data['items'],
            'totals' => $data['totals'],
        ]);
    }

    public function invoiceXlsx(string $storeCode, string $invoiceNumber)
    {
        return app(MovementInvoiceService::class)->exportXlsx($storeCode, $invoiceNumber);
    }

    public function invoicePdf(string $storeCode, string $invoiceNumber)
    {
        return app(MovementInvoiceService::class)->exportPdf($storeCode, $invoiceNumber);
    }

    // =============================================
    // LIST EXPORTS (xlsx / pdf) — respeitam filtros do index
    // =============================================

    public function exportXlsx(Request $request)
    {
        set_time_limit(0);

        ['query' => $query, 'filters' => $filters] = $this->buildFilteredQuery($request);

        return app(MovementListExportService::class)->exportXlsx($query, $filters);
    }

    public function exportPdf(Request $request)
    {
        set_time_limit(0);

        ['query' => $query, 'filters' => $filters] = $this->buildFilteredQuery($request);

        return app(MovementListExportService::class)->exportPdf($query, $filters);
    }

    // =============================================
    // SYNC ENDPOINTS (synchronous, no timeout)
    // =============================================

    public function syncAuto()
    {
        set_time_limit(0);

        $log = MovementSyncLog::start('auto', auth()->id());
        $service = app(MovementSyncService::class);
        $service->syncAutomatic(auth()->id(), $log);

        return response()->json($this->formatProgress($log->fresh()));
    }

    public function syncToday()
    {
        set_time_limit(0);

        $log = MovementSyncLog::start('today', auth()->id());
        $service = app(MovementSyncService::class);
        $service->syncToday(auth()->id(), $log);

        return response()->json($this->formatProgress($log->fresh()));
    }

    public function syncByMonth(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        set_time_limit(0);

        $log = MovementSyncLog::start('range', auth()->id());
        $service = app(MovementSyncService::class);
        $service->syncByMonth((int) $request->month, (int) $request->year, auth()->id(), $log);

        return response()->json($this->formatProgress($log->fresh()));
    }

    public function syncByDateRange(Request $request)
    {
        $request->validate([
            'date_start' => 'required|date',
            'date_end' => 'required|date|after_or_equal:date_start',
        ]);

        set_time_limit(0);

        $log = MovementSyncLog::start('range', auth()->id());
        $service = app(MovementSyncService::class);
        $service->syncByDateRange(
            Carbon::parse($request->date_start),
            Carbon::parse($request->date_end),
            auth()->id(),
            $log,
        );

        return response()->json($this->formatProgress($log->fresh()));
    }

    public function syncMovementTypes()
    {
        $service = app(MovementSyncService::class);

        if (! $service->isAvailable()) {
            return response()->json([
                'status' => 'failed',
                'error_details' => ['message' => $service->getUnavailableReason() ?? 'CIGAM indisponivel'],
            ], 422);
        }

        $log = MovementSyncLog::start('types', auth()->id());

        try {
            $count = $service->syncMovementTypes();
            $log->update([
                'total_records' => $count,
                'processed_records' => $count,
                'inserted_records' => $count,
            ]);
            $log->markCompleted();
        } catch (\Exception $e) {
            $log->markFailed($e->getMessage());
        }

        return response()->json($this->formatProgress($log->fresh()));
    }

    // =============================================
    // PROGRESS
    // =============================================

    public function syncProgress(MovementSyncLog $log)
    {
        return response()->json($this->formatProgress($log));
    }

    protected function formatProgress(MovementSyncLog $log): array
    {
        return [
            'id' => $log->id,
            'sync_type' => $log->sync_type,
            'status' => $log->status,
            'total_records' => $log->total_records ?? 0,
            'processed_records' => $log->processed_records ?? 0,
            'inserted_records' => $log->inserted_records ?? 0,
            'deleted_records' => $log->deleted_records ?? 0,
            'skipped_records' => $log->skipped_records ?? 0,
            'error_count' => $log->error_count ?? 0,
            'error_details' => $log->error_details,
            'deletion_summary' => $log->deletion_summary,
            'date_range_start' => $log->date_range_start?->format('d/m/Y'),
            'date_range_end' => $log->date_range_end?->format('d/m/Y'),
            'started_at' => $log->started_at?->format('d/m/Y H:i:s'),
            'completed_at' => $log->completed_at?->format('d/m/Y H:i:s'),
            'elapsed_seconds' => $log->started_at
                ? $log->started_at->diffInSeconds($log->completed_at ?? now())
                : 0,
            'percentage' => $log->total_records > 0
                ? min(100, round(($log->processed_records / $log->total_records) * 100))
                : ($log->status === 'completed' ? 100 : 0),
        ];
    }

    public function syncLogs()
    {
        $logs = MovementSyncLog::with('startedBy')
            ->orderByDesc('started_at')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'sync_type' => $log->sync_type,
                'status' => $log->status,
                'total_records' => $log->total_records ?? 0,
                'processed_records' => $log->processed_records ?? 0,
                'inserted_records' => $log->inserted_records ?? 0,
                'deleted_records' => $log->deleted_records ?? 0,
                'skipped_records' => $log->skipped_records ?? 0,
                'error_count' => $log->error_count ?? 0,
                'error_message' => $log->error_details['message'] ?? null,
                'error_records' => $log->error_details['records'] ?? [],
                'error_truncated' => $log->error_details['truncated'] ?? 0,
                'deletion_summary' => $log->deletion_summary,
                'date_range_start' => $log->date_range_start?->format('d/m/Y'),
                'date_range_end' => $log->date_range_end?->format('d/m/Y'),
                'started_at' => $log->started_at?->format('d/m/Y H:i:s'),
                'completed_at' => $log->completed_at?->format('d/m/Y H:i:s'),
                'elapsed_seconds' => $log->started_at && $log->completed_at
                    ? $log->started_at->diffInSeconds($log->completed_at)
                    : null,
                'started_by' => $log->startedBy?->name ?? 'Sistema',
            ]);

        // Summary info
        $lastCompleted = MovementSyncLog::where('status', 'completed')
            ->orderByDesc('completed_at')
            ->first();

        $lastMovementDate = \App\Models\Movement::max('movement_date');
        $totalMovements = \App\Models\Movement::count();
        $totalSyncs = MovementSyncLog::count();
        $failedSyncs = MovementSyncLog::where('status', 'failed')->count();

        return response()->json([
            'logs' => $logs,
            'summary' => [
                'last_sync_at' => $lastCompleted?->completed_at?->format('d/m/Y H:i:s'),
                'last_sync_type' => $lastCompleted?->sync_type,
                'last_sync_records' => $lastCompleted?->inserted_records ?? 0,
                'last_sync_elapsed' => $lastCompleted?->started_at && $lastCompleted?->completed_at
                    ? $lastCompleted->started_at->diffInSeconds($lastCompleted->completed_at)
                    : null,
                'last_movement_date' => $lastMovementDate
                    ? Carbon::parse($lastMovementDate)->format('d/m/Y')
                    : null,
                'total_movements' => $totalMovements,
                'total_syncs' => $totalSyncs,
                'failed_syncs' => $failedSyncs,
            ],
        ]);
    }

    public function refreshSalesOnly(Request $request)
    {
        $request->validate([
            'date_start' => 'required|date',
            'date_end' => 'required|date|after_or_equal:date_start',
        ]);

        $service = app(MovementSyncService::class);
        $result = $service->refreshSalesSummary(
            Carbon::parse($request->date_start),
            Carbon::parse($request->date_end)
        );

        return back()->with('success', "Vendas atualizadas: {$result['inserted']} inseridas, {$result['updated']} atualizadas.");
    }
}
