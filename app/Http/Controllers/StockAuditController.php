<?php

namespace App\Http\Controllers;

use App\Models\StockAudit;
use App\Models\StockAuditArea;
use App\Models\StockAuditItem;
use App\Models\StockAuditLog;
use App\Models\StockAuditSignature;
use App\Models\StockAuditStoreJustification;
use App\Models\StockAuditTeam;
use App\Models\Store;
use App\Services\StockAuditCigamService;
use App\Services\StockAuditCountingService;
use App\Services\StockAuditImportService;
use App\Services\StockAuditPendencyService;
use App\Services\StockAuditReconciliationService;
use App\Services\StockAuditReportService;
use App\Services\StockAuditTransitionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockAuditController extends Controller
{
    public function __construct(
        private StockAuditTransitionService $transitionService,
        private StockAuditCountingService $countingService,
        private StockAuditReconciliationService $reconciliationService,
        private StockAuditImportService $importService,
        private StockAuditCigamService $cigamService,
        private StockAuditReportService $reportService,
    ) {}

    // ==========================================
    // CRUD
    // ==========================================

    public function index(Request $request)
    {
        $query = StockAudit::with(['store', 'vendor', 'createdBy'])
            ->active()
            ->latest();

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }
        if ($request->filled('status')) {
            $query->forStatus($request->status);
        }
        if ($request->filled('audit_type')) {
            $query->forType($request->audit_type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('store', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $audits = $query->paginate(15)->through(fn ($audit) => $this->formatAudit($audit));

        $statusCounts = [];
        foreach (array_keys(StockAudit::STATUS_LABELS) as $status) {
            $statusCounts[$status] = StockAudit::active()->forStatus($status)->count();
        }

        return Inertia::render('StockAudits/Index', [
            'audits' => $audits,
            'filters' => $request->only(['search', 'status', 'store_id', 'audit_type']),
            'statusOptions' => StockAudit::STATUS_LABELS,
            'statusCounts' => $statusCounts,
            'typeOptions' => StockAudit::AUDIT_TYPES,
            'stores' => Store::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'audit_type' => 'required|in:total,parcial,especifica,aleatoria,diaria',
            'vendor_id' => 'nullable|exists:stock_audit_vendors,id',
            'audit_cycle_id' => 'nullable|exists:stock_audit_cycles,id',
            'manager_responsible_id' => 'nullable|exists:employees,id',
            'stockist_id' => 'nullable|exists:employees,id',
            'random_sample_size' => 'nullable|integer|min:10',
            'requires_second_count' => 'boolean',
            'requires_third_count' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Total audits always require second count
        if ($validated['audit_type'] === 'total') {
            $validated['requires_second_count'] = true;
        }

        $validated['status'] = 'draft';
        $validated['created_by_user_id'] = auth()->id();

        $audit = StockAudit::create($validated);

        StockAuditLog::create([
            'audit_id' => $audit->id,
            'action_type' => 'created',
            'new_status' => 'draft',
            'changed_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('stock-audits.index')
            ->with('success', 'Auditoria #'.$audit->id.' criada com sucesso.');
    }

    public function show(StockAudit $stockAudit)
    {
        return response()->json($this->formatAuditDetailed($stockAudit));
    }

    public function update(Request $request, StockAudit $stockAudit)
    {
        if ($stockAudit->status !== 'draft') {
            return back()->withErrors(['stock_audit' => 'Apenas auditorias em rascunho podem ser editadas.']);
        }

        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'audit_type' => 'required|in:total,parcial,especifica,aleatoria,diaria',
            'vendor_id' => 'nullable|exists:stock_audit_vendors,id',
            'audit_cycle_id' => 'nullable|exists:stock_audit_cycles,id',
            'manager_responsible_id' => 'nullable|exists:employees,id',
            'stockist_id' => 'nullable|exists:employees,id',
            'random_sample_size' => 'nullable|integer|min:10',
            'requires_second_count' => 'boolean',
            'requires_third_count' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $validated['updated_by_user_id'] = auth()->id();
        $stockAudit->update($validated);

        return redirect()->route('stock-audits.index')
            ->with('success', 'Auditoria #'.$stockAudit->id.' atualizada.');
    }

    public function destroy(StockAudit $stockAudit)
    {
        if ($stockAudit->status !== 'draft') {
            return back()->withErrors(['stock_audit' => 'Apenas auditorias em rascunho podem ser excluidas.']);
        }

        $stockAudit->update([
            'deleted_at' => now(),
            'deleted_by_user_id' => auth()->id(),
        ]);

        return redirect()->route('stock-audits.index')
            ->with('success', 'Auditoria #'.$stockAudit->id.' excluida.');
    }

    // ==========================================
    // Transitions
    // ==========================================

    public function transition(Request $request, StockAudit $stockAudit)
    {
        $validated = $request->validate([
            'new_status' => 'required|string',
            'notes' => 'nullable|string',
            'cancellation_reason' => 'nullable|string',
        ]);

        $validation = $this->transitionService->validateTransition($stockAudit, $validated['new_status'], $validated);

        if (! $validation['valid']) {
            return response()->json([
                'error' => true,
                'message' => implode(' ', $validation['errors']),
            ], 422);
        }

        $this->transitionService->executeTransition(
            $stockAudit,
            $validated['new_status'],
            $validated,
            auth()->id()
        );

        $label = StockAudit::STATUS_LABELS[$validated['new_status']] ?? $validated['new_status'];

        return response()->json([
            'error' => false,
            'message' => "Status atualizado para: {$label}",
        ]);
    }

    // ==========================================
    // Counting
    // ==========================================

    public function counting(StockAudit $stockAudit)
    {
        $stockAudit->load(['store', 'areas', 'teams.user']);
        $summary = $this->countingService->getCountingSummary($stockAudit);

        $items = $stockAudit->items()
            ->orderBy('product_reference')
            ->paginate(50)
            ->through(fn ($item) => [
                'id' => $item->id,
                'product_reference' => $item->product_reference,
                'product_description' => $item->product_description,
                'product_barcode' => $item->product_barcode,
                'product_size' => $item->product_size,
                'system_quantity' => (float) $item->system_quantity,
                'count_1' => $item->count_1 !== null ? (float) $item->count_1 : null,
                'count_2' => $item->count_2 !== null ? (float) $item->count_2 : null,
                'count_3' => $item->count_3 !== null ? (float) $item->count_3 : null,
                'area_id' => $item->area_id,
            ]);

        return Inertia::render('StockAudits/Counting', [
            'audit' => $this->formatAudit($stockAudit),
            'items' => $items,
            'areas' => $stockAudit->areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name]),
            'summary' => $summary,
        ]);
    }

    public function count(Request $request, StockAudit $stockAudit)
    {
        $validated = $request->validate([
            'barcode' => 'required|string',
            'round' => 'required|integer|in:1,2,3',
            'quantity' => 'nullable|numeric|min:0.01',
            'area_id' => 'nullable|exists:stock_audit_areas,id',
        ]);

        $result = $this->countingService->registerScan(
            $stockAudit,
            $validated['barcode'],
            $validated['round'],
            $validated['quantity'] ?? 1,
            $validated['area_id'] ?? null,
            auth()->id()
        );

        return response()->json($result, $result['error'] ? 422 : 200);
    }

    public function clearCount(Request $request, StockAudit $stockAudit)
    {
        $validated = $request->validate([
            'round' => 'required|integer|in:1,2,3',
            'area_id' => 'nullable|exists:stock_audit_areas,id',
        ]);

        $result = $this->countingService->clearRound(
            $stockAudit,
            $validated['round'],
            $validated['area_id'] ?? null,
            auth()->id()
        );

        return response()->json($result, $result['error'] ? 422 : 200);
    }

    public function finalizeRound(Request $request, StockAudit $stockAudit)
    {
        $validated = $request->validate([
            'round' => 'required|integer|in:1,2,3',
        ]);

        $result = $this->countingService->finalizeRound($stockAudit, $validated['round'], auth()->id());

        return response()->json($result, $result['error'] ? 422 : 200);
    }

    public function importCount(Request $request, StockAudit $stockAudit)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
            'round' => 'required|integer|in:1,2,3',
            'area_id' => 'nullable|exists:stock_audit_areas,id',
        ]);

        $result = $this->importService->processImport(
            $stockAudit,
            $request->file('file'),
            $validated['round'],
            $validated['area_id'] ?? null,
            auth()->id()
        );

        return response()->json($result, isset($result['error']) && $result['error'] ? 422 : 200);
    }

    // ==========================================
    // Reconciliation
    // ==========================================

    public function reconciliation(StockAudit $stockAudit)
    {
        $stockAudit->load(['store', 'areas']);

        $items = $stockAudit->items()
            ->with('storeJustifications.submittedBy')
            ->orderBy('divergence')
            ->paginate(50)
            ->through(fn ($item) => [
                'id' => $item->id,
                'product_reference' => $item->product_reference,
                'product_description' => $item->product_description,
                'product_barcode' => $item->product_barcode,
                'product_size' => $item->product_size,
                'system_quantity' => (float) $item->system_quantity,
                'count_1' => $item->count_1 !== null ? (float) $item->count_1 : null,
                'count_2' => $item->count_2 !== null ? (float) $item->count_2 : null,
                'count_3' => $item->count_3 !== null ? (float) $item->count_3 : null,
                'accepted_count' => $item->accepted_count !== null ? (float) $item->accepted_count : null,
                'resolution_type' => $item->resolution_type,
                'divergence' => (float) $item->divergence,
                'divergence_value' => (float) $item->divergence_value,
                'is_justified' => $item->is_justified,
                'justification_note' => $item->justification_note,
                'store_justified' => $item->store_justified,
                'store_justifications' => $item->storeJustifications->map(fn ($j) => [
                    'id' => $j->id,
                    'text' => $j->justification_text,
                    'found_quantity' => $j->found_quantity,
                    'review_status' => $j->review_status,
                    'submitted_by' => $j->submittedBy?->name,
                    'submitted_at' => $j->submitted_at?->format('d/m/Y H:i'),
                ]),
            ]);

        return Inertia::render('StockAudits/Reconciliation', [
            'audit' => $this->formatAudit($stockAudit),
            'items' => $items,
            'areas' => $stockAudit->areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name]),
        ]);
    }

    public function reconcilePhaseA(Request $request, StockAudit $stockAudit)
    {
        $action = $request->input('action', 'auto');

        if ($action === 'auto') {
            $result = $this->reconciliationService->autoResolvePhaseA($stockAudit);

            return response()->json($result);
        }

        // Manual resolve
        $validated = $request->validate([
            'item_id' => 'required|exists:stock_audit_items,id',
            'accepted_count' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $item = StockAuditItem::findOrFail($validated['item_id']);
        $result = $this->reconciliationService->manualResolve(
            $item,
            $validated['accepted_count'],
            $validated['note'] ?? null,
            auth()->id()
        );

        return response()->json(['item' => $result]);
    }

    public function reconcilePhaseB(Request $request, StockAudit $stockAudit)
    {
        $action = $request->input('action', 'calculate');

        if ($action === 'calculate') {
            $result = $this->reconciliationService->calculateDivergences($stockAudit);

            return response()->json($result);
        }

        // Justify item
        $validated = $request->validate([
            'item_id' => 'required|exists:stock_audit_items,id',
            'justification_note' => 'required|string|max:1000',
        ]);

        $item = StockAuditItem::findOrFail($validated['item_id']);
        $result = $this->reconciliationService->justifyItem(
            $item,
            $validated['justification_note'],
            auth()->id()
        );

        return response()->json(['item' => $result]);
    }

    public function submitJustification(Request $request, StockAudit $stockAudit)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:stock_audit_items,id',
            'justification_text' => 'required|string|max:2000',
            'found_quantity' => 'nullable|numeric|min:0',
        ]);

        $item = StockAuditItem::findOrFail($validated['item_id']);
        $justification = $this->reconciliationService->submitStoreJustification(
            $item,
            $validated,
            auth()->id()
        );

        return response()->json(['justification' => $justification]);
    }

    public function reviewJustification(Request $request, StockAudit $stockAudit)
    {
        $validated = $request->validate([
            'justification_id' => 'required|exists:stock_audit_store_justifications,id',
            'review_status' => 'required|in:accepted,rejected',
            'review_note' => 'nullable|string|max:1000',
        ]);

        $justification = StockAuditStoreJustification::findOrFail($validated['justification_id']);
        $result = $this->reconciliationService->reviewJustification(
            $justification,
            $validated['review_status'],
            $validated['review_note'] ?? null,
            auth()->id()
        );

        return response()->json(['justification' => $result]);
    }

    // ==========================================
    // Signatures
    // ==========================================

    public function sign(Request $request, StockAudit $stockAudit)
    {
        $validated = $request->validate([
            'signer_role' => 'required|in:gerente,auditor,supervisor',
            'signature_data' => 'required|string',
        ]);

        $signature = StockAuditSignature::create([
            'audit_id' => $stockAudit->id,
            'signer_user_id' => auth()->id(),
            'signer_role' => $validated['signer_role'],
            'signature_data' => $validated['signature_data'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'signed_at' => now(),
        ]);

        return response()->json(['signature_id' => $signature->id]);
    }

    // ==========================================
    // Teams & Areas
    // ==========================================

    public function areas(Request $request, StockAudit $stockAudit)
    {
        $action = $request->input('action', 'create');

        if ($action === 'create') {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'barcode_label' => 'nullable|string|max:20',
                'sort_order' => 'nullable|integer',
            ]);
            $validated['audit_id'] = $stockAudit->id;
            $area = StockAuditArea::create($validated);

            return response()->json(['area' => $area]);
        }

        if ($action === 'update') {
            $validated = $request->validate([
                'area_id' => 'required|exists:stock_audit_areas,id',
                'name' => 'required|string|max:100',
            ]);
            $area = StockAuditArea::findOrFail($validated['area_id']);
            $area->update(['name' => $validated['name']]);

            return response()->json(['area' => $area]);
        }

        if ($action === 'delete') {
            $validated = $request->validate(['area_id' => 'required|exists:stock_audit_areas,id']);
            StockAuditArea::findOrFail($validated['area_id'])->delete();

            return response()->json(['message' => 'Area removida.']);
        }

        return response()->json(['error' => 'Acao invalida.'], 422);
    }

    public function teams(Request $request, StockAudit $stockAudit)
    {
        $action = $request->input('action', 'create');

        if ($action === 'create') {
            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'vendor_id' => 'nullable|exists:stock_audit_vendors,id',
                'external_staff_name' => 'nullable|string|max:100',
                'external_staff_document' => 'nullable|string|max:20',
                'role' => 'required|in:contador,conferente,auditor,supervisor',
                'is_third_party' => 'boolean',
            ]);
            $validated['audit_id'] = $stockAudit->id;
            $member = StockAuditTeam::create($validated);

            return response()->json(['member' => $member->load('user')]);
        }

        if ($action === 'delete') {
            $validated = $request->validate(['team_id' => 'required|exists:stock_audit_teams,id']);
            StockAuditTeam::findOrFail($validated['team_id'])->delete();

            return response()->json(['message' => 'Membro removido.']);
        }

        return response()->json(['error' => 'Acao invalida.'], 422);
    }

    // ==========================================
    // Reports & Statistics
    // ==========================================

    public function report(StockAudit $stockAudit)
    {
        $pdf = $this->reportService->generatePdf($stockAudit);

        return $pdf->download("auditoria-estoque-{$stockAudit->id}.pdf");
    }

    public function statistics()
    {
        $audits = StockAudit::active()->get();

        return response()->json([
            'total' => $audits->count(),
            'by_status' => $audits->groupBy('status')->map->count(),
            'by_type' => $audits->groupBy('audit_type')->map->count(),
            'avg_accuracy' => $audits->where('status', 'finished')->avg('accuracy_percentage'),
        ]);
    }

    public function pendencies(StockAudit $stockAudit)
    {
        $store = Store::find($stockAudit->store_id);
        if (! $store) {
            return response()->json([]);
        }

        $pendencyService = app(StockAuditPendencyService::class);
        $pendencies = $pendencyService->getAllPendencies($store->code);

        return response()->json($pendencies);
    }

    public function accuracyHistory(Request $request)
    {
        $storeId = $request->input('store_id');
        $history = $this->reportService->getAccuracyHistory($storeId);

        return response()->json($history);
    }

    // ==========================================
    // Private Helpers
    // ==========================================

    private function formatAudit(StockAudit $audit): array
    {
        return [
            'id' => $audit->id,
            'store_id' => $audit->store_id,
            'store_name' => $audit->store?->name,
            'store_code' => $audit->store?->code,
            'audit_type' => $audit->audit_type,
            'audit_type_label' => StockAudit::AUDIT_TYPES[$audit->audit_type] ?? $audit->audit_type,
            'status' => $audit->status,
            'status_label' => StockAudit::STATUS_LABELS[$audit->status] ?? $audit->status,
            'status_color' => StockAudit::STATUS_COLORS[$audit->status] ?? '',
            'vendor_name' => $audit->vendor?->company_name,
            'accuracy_percentage' => $audit->accuracy_percentage,
            'total_items_counted' => $audit->total_items_counted,
            'total_divergences' => $audit->total_divergences,
            'financial_loss' => (float) $audit->financial_loss,
            'financial_surplus' => (float) $audit->financial_surplus,
            'requires_second_count' => $audit->requires_second_count,
            'requires_third_count' => $audit->requires_third_count,
            'count_1_finalized' => $audit->count_1_finalized,
            'count_2_finalized' => $audit->count_2_finalized,
            'count_3_finalized' => $audit->count_3_finalized,
            'reconciliation_phase' => $audit->reconciliation_phase,
            'created_by' => $audit->createdBy?->name,
            'created_at' => $audit->created_at?->format('d/m/Y H:i'),
            'authorized_at' => $audit->authorized_at?->format('d/m/Y H:i'),
            'started_at' => $audit->started_at?->format('d/m/Y H:i'),
            'finished_at' => $audit->finished_at?->format('d/m/Y H:i'),
        ];
    }

    private function formatAuditDetailed(StockAudit $audit): array
    {
        $audit->load([
            'store', 'cycle', 'vendor', 'managerResponsible', 'stockist',
            'createdBy', 'authorizedBy', 'cancelledBy',
            'areas', 'teams.user', 'teams.vendor',
            'signatures.signerUser', 'importLogs', 'logs.changedBy',
        ]);

        $base = $this->formatAudit($audit);

        $itemStats = [
            'total' => $audit->items()->count(),
            'counted' => $audit->items()->whereNotNull('count_1')->count(),
            'divergent' => $audit->items()->where('divergence', '!=', 0)->count(),
            'justified' => $audit->items()->where('is_justified', true)->count(),
        ];

        return array_merge($base, [
            'cycle_name' => $audit->cycle?->cycle_name,
            'manager_name' => $audit->managerResponsible?->name ?? $audit->managerResponsible?->short_name,
            'stockist_name' => $audit->stockist?->name ?? $audit->stockist?->short_name,
            'authorized_by' => $audit->authorizedBy?->name,
            'cancelled_by' => $audit->cancelledBy?->name,
            'cancelled_at' => $audit->cancelled_at?->format('d/m/Y H:i'),
            'cancellation_reason' => $audit->cancellation_reason,
            'random_sample_size' => $audit->random_sample_size,
            'notes' => $audit->notes,
            'financial_loss_cost' => (float) $audit->financial_loss_cost,
            'financial_surplus_cost' => (float) $audit->financial_surplus_cost,
            'item_stats' => $itemStats,
            'areas' => $audit->areas->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'barcode_label' => $a->barcode_label,
                'items_count' => $audit->items()->where('area_id', $a->id)->count(),
            ]),
            'teams' => $audit->teams->map(fn ($t) => [
                'id' => $t->id,
                'user_name' => $t->user?->name,
                'vendor_name' => $t->vendor?->company_name,
                'external_staff_name' => $t->external_staff_name,
                'role' => $t->role,
                'role_label' => StockAudit::TEAM_ROLES[$t->role] ?? $t->role,
                'is_third_party' => $t->is_third_party,
            ]),
            'signatures' => $audit->signatures->map(fn ($s) => [
                'id' => $s->id,
                'signer_name' => $s->signerUser?->name,
                'signer_role' => $s->signer_role,
                'signed_at' => $s->signed_at?->format('d/m/Y H:i'),
            ]),
            'import_logs' => $audit->importLogs->map(fn ($l) => [
                'id' => $l->id,
                'round' => $l->count_round,
                'file_name' => $l->file_name,
                'format' => $l->format_type,
                'total' => $l->total_rows,
                'success' => $l->success_rows,
                'errors' => $l->error_rows,
                'created_at' => $l->created_at?->format('d/m/Y H:i'),
            ]),
            'logs' => $audit->logs()->latest()->limit(20)->get()->map(fn ($l) => [
                'action' => $l->action_type,
                'old_status' => $l->old_status ? (StockAudit::STATUS_LABELS[$l->old_status] ?? $l->old_status) : null,
                'new_status' => $l->new_status ? (StockAudit::STATUS_LABELS[$l->new_status] ?? $l->new_status) : null,
                'user' => $l->changedBy?->name,
                'notes' => $l->notes,
                'created_at' => $l->created_at?->format('d/m/Y H:i'),
            ]),
            'available_transitions' => StockAudit::VALID_TRANSITIONS[$audit->status] ?? [],
        ]);
    }
}
