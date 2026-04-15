<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Models\Brand;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderBarcodeService;
use App\Services\PurchaseOrderCigamMatcherService;
use App\Services\PurchaseOrderExportService;
use App\Services\PurchaseOrderImportService;
use App\Services\PurchaseOrderReceiptService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseOrderTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderService $service,
        private PurchaseOrderTransitionService $transitionService,
        private PurchaseOrderReceiptService $receiptService,
        private PurchaseOrderCigamMatcherService $cigamMatcher,
        private PurchaseOrderImportService $importService,
        private PurchaseOrderExportService $exportService,
        private PurchaseOrderBarcodeService $barcodeService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $query = PurchaseOrder::with(['supplier', 'store', 'brand'])
            ->withCount('items')
            ->notDeleted()
            ->latest();

        if ($scopedStoreCode) {
            $query->forStore($scopedStoreCode);
        } elseif ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        } elseif (! $request->boolean('include_terminal')) {
            // Default: esconde canceladas e entregues antigas
            $query->whereNotIn('status', [
                PurchaseOrderStatus::CANCELLED->value,
                PurchaseOrderStatus::DELIVERED->value,
            ]);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('season', 'like', "%{$search}%")
                    ->orWhere('collection', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('order_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->date_to);
        }

        $orders = $query->paginate(15)->through(fn ($o) => $this->formatOrder($o));

        $statistics = $this->buildStatistics($scopedStoreCode);

        return Inertia::render('PurchaseOrders/Index', [
            'orders' => $orders,
            'filters' => $request->only([
                'store_id', 'status', 'supplier_id', 'brand_id',
                'search', 'date_from', 'date_to', 'include_terminal',
            ]),
            'statistics' => $statistics,
            'statusOptions' => PurchaseOrderStatus::labels(),
            'statusColors' => PurchaseOrderStatus::colors(),
            'statusTransitions' => PurchaseOrderStatus::transitionMap(),
            'isStoreScoped' => $scopedStoreCode !== null,
            'scopedStoreCode' => $scopedStoreCode,
            'selects' => [
                'stores' => $scopedStoreCode
                    ? Store::where('code', $scopedStoreCode)->get(['id', 'code', 'name'])
                    : Store::orderBy('name')->get(['id', 'code', 'name']),
                'suppliers' => Supplier::active()
                    ->orderBy('nome_fantasia')
                    ->get(['id', 'nome_fantasia', 'razao_social', 'payment_terms_default']),
                'brands' => Brand::orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $data = $request->validate([
            'order_number' => 'required|string|max:50',
            'short_description' => 'nullable|string|max:255',
            'season' => 'required|string|max:120',
            'collection' => 'required|string|max:120',
            'release_name' => 'required|string|max:120',
            'supplier_id' => 'required|exists:suppliers,id',
            'store_id' => 'required|string|max:10|exists:stores,code',
            'brand_id' => 'nullable|exists:brands,id',
            'order_date' => 'required|date',
            'predict_date' => 'nullable|date',
            'payment_terms_raw' => 'nullable|string|max:150',
            'auto_generate_payments' => 'nullable|boolean',
            'notes' => 'nullable|string|max:5000',
        ]);

        if ($scopedStoreCode && $data['store_id'] !== $scopedStoreCode) {
            return redirect()->back()->withErrors([
                'store_id' => 'Você só pode criar ordens para a sua própria loja.',
            ])->withInput();
        }

        try {
            $this->service->create($data, $user);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('purchase-orders.index')
            ->with('success', 'Ordem de compra criada com sucesso.');
    }

    public function show(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $this->ensureCanView($request->user(), $purchaseOrder);

        $purchaseOrder->load([
            'supplier', 'store', 'brand', 'createdBy', 'updatedBy',
            'items',
            'receipts.items.purchaseOrderItem',
            'receipts.createdBy',
            'statusHistory.changedBy',
        ]);

        return response()->json([
            'order' => $this->formatOrderDetailed($purchaseOrder),
        ]);
    }

    public function update(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $purchaseOrder);

        $data = $request->validate([
            'order_number' => 'required|string|max:50',
            'short_description' => 'nullable|string|max:255',
            'season' => 'required|string|max:120',
            'collection' => 'required|string|max:120',
            'release_name' => 'required|string|max:120',
            'supplier_id' => 'required|exists:suppliers,id',
            'brand_id' => 'nullable|exists:brands,id',
            'order_date' => 'required|date',
            'predict_date' => 'nullable|date',
            'payment_terms_raw' => 'nullable|string|max:150',
            'auto_generate_payments' => 'nullable|boolean',
            'notes' => 'nullable|string|max:5000',
        ]);

        try {
            $this->service->update($purchaseOrder, $data, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('purchase-orders.index')
            ->with('success', 'Ordem de compra atualizada.');
    }

    public function destroy(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $purchaseOrder);

        $data = $request->validate([
            'deleted_reason' => 'required|string|min:3|max:500',
        ]);

        try {
            $this->service->delete($purchaseOrder, $request->user(), $data['deleted_reason']);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->route('purchase-orders.index')
            ->with('success', 'Ordem de compra excluída.');
    }

    public function transition(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $purchaseOrder);

        $data = $request->validate([
            'to_status' => 'required|in:pending,invoiced,partial_invoiced,cancelled,delivered',
            'note' => 'nullable|string|max:2000',
        ]);

        try {
            $this->transitionService->transition(
                $purchaseOrder,
                $data['to_status'],
                $request->user(),
                $data['note'] ?? null
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->route('purchase-orders.index')
            ->with('success', 'Status da ordem atualizado.');
    }

    // ------------------------------------------------------------------
    // Item management — endpoints nested para criar/editar/remover itens
    // ------------------------------------------------------------------

    public function storeItems(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $purchaseOrder);

        if ($purchaseOrder->status !== PurchaseOrderStatus::PENDING) {
            return redirect()->back()->withErrors([
                'status' => 'Itens só podem ser adicionados em ordens pendentes.',
            ]);
        }

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.reference' => 'required|string|max:50',
            'items.*.description' => 'required|string|max:255',
            'items.*.material' => 'nullable|string|max:150',
            'items.*.color' => 'nullable|string|max:100',
            'items.*.group_name' => 'nullable|string|max:200',
            'items.*.subgroup_name' => 'nullable|string|max:200',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.markup' => 'nullable|numeric|min:0',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.pricing_locked' => 'nullable|boolean',
            // size_matrix é um objeto {size: quantity} — converte para N items
            'items.*.sizes' => 'required|array|min:1',
            'items.*.sizes.*' => 'integer|min:1',
        ]);

        DB::transaction(function () use ($purchaseOrder, $data) {
            foreach ($data['items'] as $itemData) {
                foreach ($itemData['sizes'] as $size => $quantity) {
                    PurchaseOrderItem::updateOrCreate(
                        [
                            'purchase_order_id' => $purchaseOrder->id,
                            'reference' => $itemData['reference'],
                            'size' => (string) $size,
                        ],
                        [
                            'description' => $itemData['description'],
                            'material' => $itemData['material'] ?? null,
                            'color' => $itemData['color'] ?? null,
                            'group_name' => $itemData['group_name'] ?? null,
                            'subgroup_name' => $itemData['subgroup_name'] ?? null,
                            'unit_cost' => $itemData['unit_cost'],
                            'markup' => $itemData['markup'] ?? 0,
                            'selling_price' => $itemData['selling_price'] ?? 0,
                            'pricing_locked' => $itemData['pricing_locked'] ?? false,
                            'quantity_ordered' => $quantity,
                        ]
                    );
                }
            }
        });

        return redirect()->route('purchase-orders.index')
            ->with('success', 'Itens adicionados à ordem.');
    }

    public function destroyItem(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $purchaseOrder);

        if ($item->purchase_order_id !== $purchaseOrder->id) {
            abort(404);
        }

        if ($purchaseOrder->status !== PurchaseOrderStatus::PENDING) {
            return redirect()->back()->withErrors([
                'status' => 'Itens só podem ser removidos de ordens pendentes.',
            ]);
        }

        $item->delete();

        return redirect()->route('purchase-orders.index')
            ->with('success', 'Item removido.');
    }

    // ------------------------------------------------------------------
    // Receipts (Fase 2 — recebimento manual + matcher CIGAM)
    // ------------------------------------------------------------------

    public function storeReceipt(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $purchaseOrder);

        if (! $request->user()->hasPermissionTo(Permission::RECEIVE_PURCHASE_ORDERS->value)) {
            abort(403, 'Você não tem permissão para registrar recebimentos.');
        }

        $data = $request->validate([
            'invoice_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|integer|exists:purchase_order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $this->receiptService->register(
                order: $purchaseOrder,
                items: $data['items'],
                actor: $request->user(),
                invoiceNumber: $data['invoice_number'] ?? null,
                notes: $data['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->route('purchase-orders.index')
            ->with('success', 'Recebimento registrado com sucesso.');
    }

    /**
     * Tenta casar a ordem com Movements do CIGAM. Pode criar 0+ receipts
     * de source='cigam_match'. Não dispara transição automática — usuário
     * precisa confirmar visualmente.
     */
    public function matchCigam(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $purchaseOrder);

        if (! $request->user()->hasPermissionTo(Permission::RECEIVE_PURCHASE_ORDERS->value)) {
            abort(403, 'Você não tem permissão para buscar recebimentos no CIGAM.');
        }

        $result = $this->cigamMatcher->matchOrder($purchaseOrder);

        $message = $result['receipts_created'] > 0
            ? "CIGAM: {$result['receipts_created']} recebimento(s) detectado(s) com {$result['items_matched']} item(ns)."
            : "CIGAM: nenhum movimento novo encontrado para esta ordem.";

        return redirect()->route('purchase-orders.index')->with('success', $message);
    }

    // ------------------------------------------------------------------
    // Dashboard (Fase 4 — gráficos agregados)
    // ------------------------------------------------------------------

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $base = PurchaseOrder::notDeleted();
        if ($scopedStoreCode) {
            $base->forStore($scopedStoreCode);
        }

        // Status distribution
        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        // Ordens criadas por mês (últimos 6 meses).
        // Usa SUBSTR no created_at (formato ISO 'YYYY-MM-DD HH:MM:SS') —
        // funciona tanto em MySQL quanto em SQLite (DATE_FORMAT é MySQL only).
        $sixMonthsAgo = now()->subMonths(6)->startOfMonth();
        $byMonth = (clone $base)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->selectRaw("SUBSTR(created_at, 1, 7) as month, COUNT(*) as count, COALESCE(SUM((SELECT SUM(unit_cost * quantity_ordered) FROM purchase_order_items WHERE purchase_order_id = purchase_orders.id)), 0) as total_cost")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'month' => $r->month,
                'count' => (int) $r->count,
                'total_cost' => (float) $r->total_cost,
            ])
            ->values();

        // Top 5 fornecedores por número de ordens
        $topSuppliers = (clone $base)
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->selectRaw('suppliers.id, suppliers.nome_fantasia, COUNT(*) as count')
            ->groupBy('suppliers.id', 'suppliers.nome_fantasia')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'supplier_id' => $r->id,
                'supplier_name' => $r->nome_fantasia,
                'count' => (int) $r->count,
            ])
            ->values();

        // Top 5 marcas
        $topBrands = (clone $base)
            ->whereNotNull('brand_id')
            ->join('brands', 'purchase_orders.brand_id', '=', 'brands.id')
            ->selectRaw('brands.id, brands.name, COUNT(*) as count')
            ->groupBy('brands.id', 'brands.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'brand_id' => $r->id,
                'brand_name' => $r->name,
                'count' => (int) $r->count,
            ])
            ->values();

        $overdue = (clone $base)
            ->active()
            ->whereNotNull('predict_date')
            ->whereDate('predict_date', '<', now()->toDateString())
            ->count();

        return Inertia::render('PurchaseOrders/Dashboard', [
            'isStoreScoped' => $scopedStoreCode !== null,
            'scopedStoreCode' => $scopedStoreCode,
            'statusDistribution' => $byStatus,
            'statusLabels' => PurchaseOrderStatus::labels(),
            'statusColors' => PurchaseOrderStatus::colors(),
            'byMonth' => $byMonth,
            'topSuppliers' => $topSuppliers,
            'topBrands' => $topBrands,
            'overdueCount' => $overdue,
        ]);
    }

    // ------------------------------------------------------------------
    // Barcodes (Fase 4 — EAN-13 interno)
    // ------------------------------------------------------------------

    public function generateBarcodes(PurchaseOrder $purchaseOrder, Request $request): RedirectResponse
    {
        $this->ensureCanView($request->user(), $purchaseOrder);

        $result = $this->barcodeService->ensureForOrder($purchaseOrder);

        $msg = "Códigos de barras: {$result['generated']} novo(s), {$result['existing']} já existente(s).";
        return redirect()->route('purchase-orders.index')->with('success', $msg);
    }

    // ------------------------------------------------------------------
    // Import (Fase 3 — planilha)
    // ------------------------------------------------------------------

    public function importPage(): Response
    {
        return Inertia::render('PurchaseOrders/Import', [
            'suppliers' => Supplier::where('is_active', true)
                ->orderBy('nome_fantasia')
                ->get(['id', 'nome_fantasia', 'razao_social']),
        ]);
    }

    public function importPreview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        $path = $request->file('file')->getRealPath();
        $preview = $this->importService->preview($path, limit: 10);

        return response()->json($preview);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'default_supplier_id' => 'required|integer|exists:suppliers,id',
        ]);

        $path = $request->file('file')->getRealPath();
        $stats = $this->importService->import(
            $path,
            $request->user(),
            (int) $request->input('default_supplier_id')
        );

        $msg = "Import: {$stats['orders_created']} ordens criadas, {$stats['orders_updated']} atualizadas, "
             . "{$stats['items_created']} itens novos, {$stats['items_updated']} atualizados";
        if ($stats['rows_rejected'] > 0) {
            $msg .= " · {$stats['rows_rejected']} linhas rejeitadas";
        }

        return redirect()->route('purchase-orders.import.page')
            ->with('success', $msg)
            ->with('importStats', $stats);
    }

    // ------------------------------------------------------------------
    // Export (Fase 3 — Excel/PDF)
    // ------------------------------------------------------------------

    public function export(Request $request)
    {
        $user = $request->user();
        $scopedStoreCode = $this->resolveScopedStoreCode($user);

        $format = $request->input('format', 'excel');
        if (! in_array($format, ['excel', 'pdf'], true)) {
            return redirect()->back()->withErrors(['format' => 'Formato inválido (use excel ou pdf).']);
        }

        // Reusa os mesmos filtros do index
        $query = PurchaseOrder::with(['supplier', 'store', 'brand', 'items'])
            ->notDeleted();

        if ($scopedStoreCode) {
            $query->forStore($scopedStoreCode);
        } elseif ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('status')) {
            $query->forStatus($request->status);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('season', 'like', "%{$search}%")
                    ->orWhere('collection', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('order_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->date_to);
        }

        try {
            return $format === 'pdf'
                ? $this->exportService->exportPdf($query)
                : $this->exportService->exportExcel($query);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors(['format' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function resolveScopedStoreCode(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->hasPermissionTo(Permission::MANAGE_PURCHASE_ORDERS->value)) {
            return null;
        }

        return $user->store_id ?: null;
    }

    protected function ensureCanView(?User $user, PurchaseOrder $order): void
    {
        $scoped = $this->resolveScopedStoreCode($user);
        if ($scoped && $order->store_id !== $scoped) {
            abort(403, 'Você não tem acesso a ordens de outras lojas.');
        }
    }

    protected function buildStatistics(?string $scopedStoreCode): array
    {
        $base = PurchaseOrder::notDeleted();
        if ($scopedStoreCode) {
            $base->forStore($scopedStoreCode);
        }

        $total = (clone $base)->count();

        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $overdue = (clone $base)
            ->active()
            ->whereNotNull('predict_date')
            ->whereDate('predict_date', '<', now()->toDateString())
            ->count();

        return [
            'total' => $total,
            'pending' => $byStatus['pending'] ?? 0,
            'invoiced' => $byStatus['invoiced'] ?? 0,
            'partial_invoiced' => $byStatus['partial_invoiced'] ?? 0,
            'delivered' => $byStatus['delivered'] ?? 0,
            'cancelled' => $byStatus['cancelled'] ?? 0,
            'overdue' => $overdue,
        ];
    }

    protected function formatOrder(PurchaseOrder $o): array
    {
        return [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'short_description' => $o->short_description,
            'season' => $o->season,
            'collection' => $o->collection,
            'release_name' => $o->release_name,
            'supplier_id' => $o->supplier_id,
            'supplier_name' => $o->supplier?->nome_fantasia,
            'store_id' => $o->store_id,
            'store_name' => $o->store?->name,
            'brand_id' => $o->brand_id,
            'brand_name' => $o->brand?->name,
            'order_date' => $o->order_date?->toDateString(),
            'predict_date' => $o->predict_date?->toDateString(),
            'delivered_at' => $o->delivered_at?->toDateTimeString(),
            'payment_terms_raw' => $o->payment_terms_raw,
            'auto_generate_payments' => $o->auto_generate_payments,
            'status' => $o->status?->value,
            'status_label' => $o->status?->label(),
            'status_color' => $o->status?->color(),
            'items_count' => $o->items_count ?? $o->items()->count(),
            'is_overdue' => $o->isOverdue(),
            'is_terminal' => $o->isTerminal(),
            'created_at' => $o->created_at?->toDateTimeString(),
        ];
    }

    protected function formatOrderDetailed(PurchaseOrder $o): array
    {
        // Lookup batch de barcodes pra evitar N queries no map abaixo
        $barcodeMap = $this->barcodeService->lookupForItems($o->items);

        return array_merge($this->formatOrder($o), [
            'notes' => $o->notes,
            'total_cost' => $o->total_cost,
            'total_selling' => $o->total_selling,
            'total_units' => $o->total_units,
            'created_by_name' => $o->createdBy?->name,
            'updated_by_name' => $o->updatedBy?->name,
            'supplier' => $o->supplier ? [
                'id' => $o->supplier->id,
                'razao_social' => $o->supplier->razao_social,
                'nome_fantasia' => $o->supplier->nome_fantasia,
                'cnpj' => $o->supplier->formatted_cnpj,
                'payment_terms_default' => $o->supplier->payment_terms_default,
            ] : null,
            'items' => $o->items->map(fn ($item) => [
                'id' => $item->id,
                'reference' => $item->reference,
                'size' => $item->size,
                'description' => $item->description,
                'material' => $item->material,
                'color' => $item->color,
                'group_name' => $item->group_name,
                'subgroup_name' => $item->subgroup_name,
                'unit_cost' => (float) $item->unit_cost,
                'markup' => (float) $item->markup,
                'selling_price' => (float) $item->selling_price,
                'pricing_locked' => $item->pricing_locked,
                'quantity_ordered' => $item->quantity_ordered,
                'quantity_received' => $item->quantity_received,
                'total_cost' => $item->total_cost,
                'total_selling' => $item->total_selling,
                'invoice_number' => $item->invoice_number,
                'is_fully_received' => $item->is_fully_received,
                'barcode' => $barcodeMap[$item->reference . '|' . $item->size] ?? null,
            ])->values(),
            'status_history' => $o->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'note' => $h->note,
                'changed_by_name' => $h->changedBy?->name,
                'created_at' => $h->created_at?->toDateTimeString(),
            ])->values(),
            'receipts' => $o->receipts->map(fn ($r) => [
                'id' => $r->id,
                'received_at' => $r->received_at?->toDateTimeString(),
                'invoice_number' => $r->invoice_number,
                'notes' => $r->notes,
                'source' => $r->source,
                'is_from_cigam' => $r->isFromCigam(),
                'created_by_name' => $r->createdBy?->name,
                'total_quantity' => $r->total_quantity,
                'items' => $r->items->map(fn ($ri) => [
                    'id' => $ri->id,
                    'reference' => $ri->purchaseOrderItem?->reference,
                    'size' => $ri->purchaseOrderItem?->size,
                    'description' => $ri->purchaseOrderItem?->description,
                    'quantity_received' => $ri->quantity_received,
                    'unit_cost_cigam' => $ri->unit_cost_cigam ? (float) $ri->unit_cost_cigam : null,
                    'matched_movement_id' => $ri->matched_movement_id,
                ])->values(),
            ])->values(),
        ]);
    }
}
