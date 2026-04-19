<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\ReturnReasonCategory;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Http\Requests\ReturnOrder\StoreReturnOrderRequest;
use App\Http\Requests\ReturnOrder\TransitionReturnOrderRequest;
use App\Http\Requests\ReturnOrder\UpdateReturnOrderRequest;
use App\Models\Employee;
use App\Models\ReturnOrder;
use App\Models\ReturnOrderFile;
use App\Models\ReturnReason;
use App\Models\Store;
use App\Models\User;
use App\Services\ReturnOrderExportService;
use App\Services\ReturnOrderImportService;
use App\Services\ReturnOrderLookupService;
use App\Services\ReturnOrderService;
use App\Services\ReturnOrderTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReturnOrderController extends Controller
{
    public function __construct(
        private ReturnOrderService $service,
        private ReturnOrderLookupService $lookup,
        private ReturnOrderTransitionService $transitionService,
        private ReturnOrderExportService $exportService,
        private ReturnOrderImportService $importService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $query = ReturnOrder::with(['reason', 'employee', 'store'])
            ->notDeleted()
            ->latest();

        if ($scopedStoreCode) {
            $query->forStore($scopedStoreCode);
        } elseif ($request->filled('store_code')) {
            $query->forStore($request->store_code);
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        } elseif (! $request->boolean('include_cancelled')) {
            // No e-commerce o atendimento precisa ver as devoluções concluídas
            // no dia a dia (cliente pergunta: "minha devolução foi concluída?").
            // Só escondemos cancelled por padrão — é ruído operacional. Para ver
            // canceladas, usar include_cancelled=1 ou filtrar status=cancelled.
            $query->where('status', '!=', ReturnStatus::CANCELLED->value);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('reason_category')) {
            $query->where('reason_category', $request->reason_category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('cpf_customer', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $returns = $query->paginate(15)
            ->withQueryString()
            ->through(fn ($r) => $this->formatReturn($r));

        $statistics = $this->buildStatistics($scopedStoreCode);

        return Inertia::render('Returns/Index', [
            'returns' => $returns,
            'filters' => $request->only([
                'store_code', 'status', 'type', 'reason_category',
                'search', 'date_from', 'date_to', 'include_cancelled',
            ]),
            'statistics' => $statistics,
            'statusOptions' => ReturnStatus::labels(),
            'statusColors' => ReturnStatus::colors(),
            'statusTransitions' => ReturnStatus::transitionMap(),
            'typeOptions' => ReturnType::labels(),
            'reasonCategoryOptions' => ReturnReasonCategory::labels(),
            'reasonCategoryColors' => ReturnReasonCategory::colors(),
            'isStoreScoped' => $scopedStoreCode !== null,
            'scopedStoreCode' => $scopedStoreCode,
            'selects' => [
                'stores' => $scopedStoreCode
                    ? Store::where('code', $scopedStoreCode)->get(['id', 'code', 'name'])
                    : Store::orderBy('name')->get(['id', 'code', 'name']),
                'reasons' => ReturnReason::active()
                    ->orderBy('sort_order')
                    ->get(['id', 'code', 'name', 'category']),
            ],
        ]);
    }

    public function store(StoreReturnOrderRequest $request): RedirectResponse
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $data = $request->validated();

        // Resolve a loja (e-commerce = Z441 por default, mas operadores
        // multi-canal podem sobrescrever)
        $data['store_code_filter'] = $scopedStoreCode ?? $data['store_code'] ?? null;
        $data['movement_date_filter'] = $data['movement_date'] ?? null;

        try {
            $order = $this->service->create($data, $user);

            if ($request->hasFile('files')) {
                $this->service->attachFiles($order, $request->file('files'), $user);
            }
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Devolução criada com sucesso.');
    }

    public function show(ReturnOrder $returnOrder, Request $request): JsonResponse
    {
        $this->ensureCanView($request->user(), $returnOrder);

        $returnOrder->load([
            'reason', 'employee', 'store',
            'createdBy', 'approvedBy', 'processedBy', 'updatedBy', 'deletedBy',
            'items.movement',
            'files.uploadedBy',
            'statusHistory.changedBy',
        ]);

        return response()->json([
            'return' => $this->formatReturnDetailed($returnOrder),
        ]);
    }

    public function update(ReturnOrder $returnOrder, UpdateReturnOrderRequest $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $returnOrder);

        try {
            $this->service->update($returnOrder, $request->validated(), $request->user());

            if ($request->hasFile('files')) {
                $this->service->attachFiles($returnOrder, $request->file('files'), $request->user());
            }
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->back()->with('success', 'Devolução atualizada.');
    }

    public function destroy(ReturnOrder $returnOrder, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $returnOrder);

        if (! $request->user()->hasPermissionTo(Permission::DELETE_RETURNS->value)) {
            abort(403, 'Você não tem permissão para excluir devoluções.');
        }

        $data = $request->validate([
            'deleted_reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->service->softDelete($returnOrder, $request->user(), $data['deleted_reason']);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Devolução excluída.');
    }

    public function transition(ReturnOrder $returnOrder, TransitionReturnOrderRequest $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $returnOrder);

        $data = $request->validated();

        try {
            $this->transitionService->transition(
                $returnOrder,
                $data['to_status'],
                $request->user(),
                $data['note'] ?? null
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Status da devolução atualizado.');
    }

    /**
     * AJAX endpoint chamado pelo modal de criação ao digitar o número da NF.
     */
    public function lookupInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_number' => 'required|string|max:50',
            'store_code' => 'nullable|string|max:10',
            'movement_date' => 'nullable|date',
        ]);

        $scopedStoreCode = $this->resolveScopedStoreCode($request->user());

        // Se usuário está scoped, usa sua loja; senão, usa o que veio na
        // request; se nada, usa o default Z441 do service.
        $storeCode = $scopedStoreCode ?? ($data['store_code'] ?? null);

        $result = $this->lookup->lookupInvoice(
            $data['invoice_number'],
            $storeCode,
            $data['movement_date'] ?? null
        );

        // Enriquece items com employee_id quando possível
        if ($result['found'] && $result['cpf_consultant']) {
            $employee = Employee::where('cpf', $result['cpf_consultant'])->first();
            $result['suggested_employee_id'] = $employee?->id;
        } else {
            $result['suggested_employee_id'] = null;
        }

        return response()->json($result);
    }

    public function statistics(Request $request): JsonResponse
    {
        $scopedStoreCode = $this->resolveScopedStoreCode($request->user());

        return response()->json(
            $this->buildStatistics($scopedStoreCode)
        );
    }

    /**
     * Dashboard — stub para Fase 5 (implementação completa com recharts).
     */
    public function dashboard(Request $request): Response
    {
        $scopedStoreCode = $this->resolveScopedStoreCode($request->user());

        return Inertia::render('Returns/Dashboard', [
            'statistics' => $this->buildStatistics($scopedStoreCode),
            'analytics' => $this->buildAnalytics($scopedStoreCode),
            'isStoreScoped' => $scopedStoreCode !== null,
            'scopedStoreCode' => $scopedStoreCode,
        ]);
    }

    // ------------------------------------------------------------------
    // Export / Import (Fase 5)
    // ------------------------------------------------------------------

    public function export(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $query = ReturnOrder::query()->notDeleted()->latest();

        if ($scopedStoreCode) {
            $query->forStore($scopedStoreCode);
        } elseif ($request->filled('store_code')) {
            $query->forStore($request->store_code);
        }

        if ($request->filled('status')) $query->forStatus($request->status);
        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->filled('reason_category')) $query->where('reason_category', $request->reason_category);
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('cpf_customer', 'like', "%{$search}%");
            });
        }
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->date_to);

        return $this->exportService->exportExcel($query);
    }

    public function exportPdf(ReturnOrder $returnOrder, Request $request): HttpResponse
    {
        $this->ensureCanView($request->user(), $returnOrder);

        return $this->exportService->exportPdf($returnOrder);
    }

    public function importPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $path = $data['file']->getRealPath();

        try {
            $result = $this->importService->preview($path);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Falha ao ler arquivo: '.$e->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $path = $data['file']->getRealPath();

        try {
            $result = $this->importService->import($path, $request->user());
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors([
                'file' => 'Falha no import: '.$e->getMessage(),
            ]);
        }

        return redirect()
            ->route('returns.index')
            ->with('success', sprintf(
                'Import concluído: %d criada(s), %d atualizada(s), %d ignorada(s).',
                $result['created'],
                $result['updated'],
                $result['skipped']
            ));
    }

    public function destroyFile(ReturnOrder $returnOrder, ReturnOrderFile $file, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $returnOrder);

        if ($file->return_order_id !== $returnOrder->id) {
            abort(404);
        }

        if ($returnOrder->isTerminal()) {
            return redirect()->back()->withErrors([
                'file' => 'Não é possível remover anexos de devoluções já finalizadas.',
            ]);
        }

        $this->service->deleteFile($file);

        return redirect()->back()->with('success', 'Anexo removido.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function resolveScopedStoreCode(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->hasPermissionTo(Permission::MANAGE_RETURNS->value)) {
            return null;
        }

        return $user->store_id ?: null;
    }

    protected function ensureCanView(?User $user, ReturnOrder $order): void
    {
        $scoped = $this->resolveScopedStoreCode($user);
        if ($scoped && $order->store_code !== $scoped) {
            abort(403, 'Você não tem acesso a devoluções de outras lojas.');
        }
    }

    protected function buildStatistics(?string $scopedStoreCode): array
    {
        $base = ReturnOrder::notDeleted();
        if ($scopedStoreCode) {
            $base->forStore($scopedStoreCode);
        }

        $total = (clone $base)->count();

        $byStatus = (clone $base)
            ->selectRaw('status as status_value, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status_value');

        $byType = (clone $base)
            ->selectRaw('type as type_value, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->keyBy('type_value');

        $monthAmount = (clone $base)
            ->forMonth((int) now()->format('m'), (int) now()->format('Y'))
            ->sum('amount_items');

        return [
            'total' => $total,
            'pending_approval' => $byStatus->get(ReturnStatus::PENDING->value)?->count ?? 0,
            'awaiting_product' => $byStatus->get(ReturnStatus::AWAITING_PRODUCT->value)?->count ?? 0,
            'processing' => $byStatus->get(ReturnStatus::PROCESSING->value)?->count ?? 0,
            'completed_this_month_amount' => (float) $monthAmount,
            'cancelled' => $byStatus->get(ReturnStatus::CANCELLED->value)?->count ?? 0,
            'trocas' => $byType->get(ReturnType::TROCA->value)?->count ?? 0,
            'estornos' => $byType->get(ReturnType::ESTORNO->value)?->count ?? 0,
            'creditos' => $byType->get(ReturnType::CREDITO->value)?->count ?? 0,
        ];
    }

    /**
     * Agregações para os gráficos do Dashboard.
     */
    protected function buildAnalytics(?string $scopedStoreCode): array
    {
        $baseQuery = fn () => $scopedStoreCode
            ? ReturnOrder::notDeleted()->forStore($scopedStoreCode)
            : ReturnOrder::notDeleted();

        // 1. Distribuição por categoria de motivo (pizza)
        $byCategory = $baseQuery()
            ->selectRaw('reason_category as category, COUNT(*) as count, COALESCE(SUM(amount_items), 0) as total_amount')
            ->groupBy('reason_category')
            ->orderByDesc('count')
            ->get()
            ->map(function ($r) {
                $cat = ReturnReasonCategory::tryFrom($r->category);
                return [
                    'category' => $r->category,
                    'label' => $cat?->label() ?? $r->category,
                    'color' => $cat?->color() ?? 'gray',
                    'count' => (int) $r->count,
                    'total_amount' => (float) $r->total_amount,
                ];
            })
            ->values();

        // 2. Distribuição por status (pizza)
        $byStatus = $baseQuery()
            ->selectRaw('status as status_value, COUNT(*) as count, COALESCE(SUM(amount_items), 0) as total_amount')
            ->groupBy('status')
            ->get()
            ->map(function ($r) {
                $status = ReturnStatus::tryFrom($r->status_value);
                return [
                    'status' => $r->status_value,
                    'label' => $status?->label() ?? $r->status_value,
                    'color' => $status?->color() ?? 'gray',
                    'count' => (int) $r->count,
                    'total_amount' => (float) $r->total_amount,
                ];
            })
            ->values();

        // 3. Distribuição por tipo (barra)
        $byType = $baseQuery()
            ->selectRaw('type as type_value, COUNT(*) as count, COALESCE(SUM(amount_items), 0) as total_amount')
            ->groupBy('type')
            ->get()
            ->map(function ($r) {
                $type = ReturnType::tryFrom($r->type_value);
                return [
                    'type' => $r->type_value,
                    'label' => $type?->label() ?? $r->type_value,
                    'color' => $type?->color() ?? 'gray',
                    'count' => (int) $r->count,
                    'total_amount' => (float) $r->total_amount,
                ];
            })
            ->values();

        // 4. Linha temporal — últimos 12 meses
        $twelveMonthsAgo = now()->subMonths(11)->startOfMonth();
        $timeline = $baseQuery()
            ->where('created_at', '>=', $twelveMonthsAgo)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(amount_items), 0) as total_amount")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $timelineFull = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y-m');
            $row = $timeline->get($key);
            $timelineFull[] = [
                'month' => $key,
                'label' => $month->locale('pt_BR')->isoFormat('MMM/YY'),
                'count' => (int) ($row?->count ?? 0),
                'total_amount' => (float) ($row?->total_amount ?? 0),
            ];
        }

        // 5. Performance: tempo médio criação → completed e taxa de aprovação
        $completedRows = $baseQuery()
            ->whereNotNull('completed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours')
            ->first();
        $avgHoursToComplete = (float) ($completedRows->avg_hours ?? 0);

        $processedCount = $baseQuery()
            ->whereIn('status', [ReturnStatus::COMPLETED->value, ReturnStatus::CANCELLED->value])
            ->count();
        $completedCount = $baseQuery()
            ->forStatus(ReturnStatus::COMPLETED)
            ->count();
        $approvalRate = $processedCount > 0 ? round(($completedCount / $processedCount) * 100, 1) : 0.0;

        return [
            'by_category' => $byCategory,
            'by_status' => $byStatus,
            'by_type' => $byType,
            'timeline' => $timelineFull,
            'performance' => [
                'avg_hours_to_complete' => round($avgHoursToComplete, 1),
                'avg_days_to_complete' => round($avgHoursToComplete / 24, 1),
                'approval_rate' => $approvalRate,
                'processed_count' => $processedCount,
                'completed_count' => $completedCount,
            ],
        ];
    }

    protected function formatReturn(ReturnOrder $r): array
    {
        return [
            'id' => $r->id,
            'invoice_number' => $r->invoice_number,
            'store_code' => $r->store_code,
            'store_name' => $r->store?->name,
            'movement_date' => $r->movement_date?->toDateString(),
            'customer_name' => $r->customer_name,
            'cpf_customer' => $r->cpf_customer,
            'cpf_consultant' => $r->cpf_consultant,
            'employee_id' => $r->employee_id,
            'employee_name' => $r->employee?->name,
            'sale_total' => (float) $r->sale_total,
            'type' => $r->type?->value,
            'type_label' => $r->type?->label(),
            'type_color' => $r->type?->color(),
            'amount_items' => (float) $r->amount_items,
            'refund_amount' => $r->refund_amount !== null ? (float) $r->refund_amount : null,
            'status' => $r->status?->value,
            'status_label' => $r->status?->label(),
            'status_color' => $r->status?->color(),
            'reason_category' => $r->reason_category?->value,
            'reason_category_label' => $r->reason_category?->label(),
            'reason_category_color' => $r->reason_category?->color(),
            'return_reason_id' => $r->return_reason_id,
            'reason_name' => $r->reason?->name,
            'reverse_tracking_code' => $r->reverse_tracking_code,
            'approved_at' => $r->approved_at?->toDateTimeString(),
            'completed_at' => $r->completed_at?->toDateTimeString(),
            'cancelled_at' => $r->cancelled_at?->toDateTimeString(),
            'is_terminal' => $r->isTerminal(),
            'created_at' => $r->created_at?->toDateTimeString(),
        ];
    }

    protected function formatReturnDetailed(ReturnOrder $r): array
    {
        return array_merge($this->formatReturn($r), [
            'cancelled_reason' => $r->cancelled_reason,
            'notes' => $r->notes,
            'created_by_name' => $r->createdBy?->name,
            'approved_by_name' => $r->approvedBy?->name,
            'processed_by_name' => $r->processedBy?->name,
            'updated_by_name' => $r->updatedBy?->name,
            'deleted_at' => $r->deleted_at?->toDateTimeString(),
            'deleted_reason' => $r->deleted_reason,
            'deleted_by_name' => $r->deletedBy?->name,
            'items' => $r->items->map(fn ($i) => [
                'id' => $i->id,
                'movement_id' => $i->movement_id,
                'reference' => $i->reference,
                'size' => $i->size,
                'barcode' => $i->barcode,
                'product_name' => $i->product_name,
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'subtotal' => (float) $i->subtotal,
            ])->values(),
            'files' => $r->files->map(fn ($f) => [
                'id' => $f->id,
                'file_name' => $f->file_name,
                'file_path' => $f->file_path,
                'file_type' => $f->file_type,
                'file_size' => $f->file_size,
                'uploaded_by_name' => $f->uploadedBy?->name,
                'created_at' => $f->created_at?->toDateTimeString(),
            ])->values(),
            'status_history' => $r->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status?->value,
                'to_status' => $h->to_status?->value,
                'from_status_label' => $h->from_status?->label(),
                'to_status_label' => $h->to_status?->label(),
                'note' => $h->note,
                'changed_by_name' => $h->changedBy?->name,
                'created_at' => $h->created_at?->toDateTimeString(),
            ])->values(),
        ]);
    }
}
