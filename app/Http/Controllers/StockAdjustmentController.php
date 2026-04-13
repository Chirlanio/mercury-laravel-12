<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Exports\StockAdjustmentsExport;
use App\Http\Requests\StockAdjustment\BulkTransitionRequest;
use App\Http\Requests\StockAdjustment\StoreStockAdjustmentNfRequest;
use App\Http\Requests\StockAdjustment\StoreStockAdjustmentRequest;
use App\Http\Requests\StockAdjustment\TransitionStockAdjustmentRequest;
use App\Http\Requests\StockAdjustment\UpdateStockAdjustmentRequest;
use App\Http\Requests\StockAdjustment\UploadAttachmentRequest;
use App\Models\Employee;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentAttachment;
use App\Models\StockAdjustmentItemNf;
use App\Models\StockAdjustmentReason;
use App\Models\Store;
use App\Services\StockAdjustmentNfService;
use App\Services\StockAdjustmentService;
use App\Services\StockAdjustmentTransitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly StockAdjustmentService $service,
        private readonly StockAdjustmentTransitionService $transitionService,
        private readonly StockAdjustmentNfService $nfService,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = StockAdjustment::with([
            'store:id,code,name',
            'employee:id,name',
            'createdBy:id,name',
        ])->active()->latest();

        // Escopo por loja para usuários não-admin
        $this->applyStoreScope($query, $user);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('reason_id')) {
            $query->whereHas('items', fn ($q) => $q->where('reason_id', $request->reason_id));
        }

        if ($request->filled('direction')) {
            $query->whereHas('items', fn ($q) => $q->where('direction', $request->direction));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('observation', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items', fn ($q) => $q->where('reference', 'like', "%{$search}%"));
            });
        }

        $adjustments = $query->withCount('items')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($a) => [
                'id' => $a->id,
                'store' => $a->store ? [
                    'id' => $a->store->id,
                    'code' => $a->store->code,
                    'name' => method_exists($a->store, 'getDisplayNameAttribute') ? $a->store->display_name : $a->store->name,
                ] : null,
                'employee' => $a->employee?->name,
                'status' => $a->status,
                'status_label' => $a->status_label,
                'observation' => $a->observation,
                'client_name' => $a->client_name,
                'items_count' => $a->items_count,
                'created_by' => $a->createdBy?->name,
                'created_at' => $a->created_at->format('d/m/Y H:i'),
            ]);

        $stores = Store::active()->orderedByStore()->get(['id', 'code', 'name']);

        // Estatísticas (respeitam o escopo de loja do usuário)
        $statsQuery = StockAdjustment::query()->active();
        $this->applyStoreScope($statsQuery, $user);
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->where('status', 'pending')->count(),
            'under_analysis' => (clone $statsQuery)->where('status', 'under_analysis')->count(),
            'awaiting_response' => (clone $statsQuery)->where('status', 'awaiting_response')->count(),
            'adjusted_month' => (clone $statsQuery)
                ->where('status', 'adjusted')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count(),
        ];

        return Inertia::render('StockAdjustments/Index', [
            'adjustments' => $adjustments,
            'stores' => $stores,
            'reasons' => StockAdjustmentReason::active()->orderBy('sort_order')->get(['id', 'code', 'name', 'applies_to']),
            'filters' => $request->only(['search', 'status', 'store_id', 'reason_id', 'direction', 'date_from', 'date_to']),
            'statusOptions' => StockAdjustment::STATUS_LABELS,
            'stats' => $stats,
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request)
    {
        $this->service->create($request->validated(), $request->user());

        return redirect()->route('stock-adjustments.index')
            ->with('success', 'Ajuste de estoque criado com sucesso.');
    }

    public function show(StockAdjustment $stockAdjustment, Request $request)
    {
        $this->authorizeStoreAccess($stockAdjustment, $request->user());

        $stockAdjustment->load([
            'store:id,code,name',
            'employee:id,name',
            'createdBy:id,name',
            'deletedBy:id,name',
            'items.reason:id,code,name,applies_to',
            'items.nfs',
            'nfs.createdBy:id,name',
            'statusHistory.changedBy:id,name',
            'attachments.uploadedBy:id,name',
        ]);

        $user = $request->user();

        return response()->json([
            'adjustment' => [
                'id' => $stockAdjustment->id,
                'store' => $stockAdjustment->store,
                'employee' => $stockAdjustment->employee,
                'status' => $stockAdjustment->status,
                'status_label' => $stockAdjustment->status_label,
                'observation' => $stockAdjustment->observation,
                'client_name' => $stockAdjustment->client_name,
                'created_by' => $stockAdjustment->createdBy?->name,
                'created_at' => $stockAdjustment->created_at?->format('d/m/Y H:i'),
                'updated_at' => $stockAdjustment->updated_at?->format('d/m/Y H:i'),
                'items' => $stockAdjustment->items->map(fn ($i) => [
                    'id' => $i->id,
                    'reference' => $i->reference,
                    'size' => $i->size,
                    'direction' => $i->direction,
                    'quantity' => $i->quantity,
                    'signed_quantity' => $i->signed_quantity,
                    'current_stock' => $i->current_stock,
                    'reason' => $i->reason ? ['id' => $i->reason->id, 'name' => $i->reason->name, 'code' => $i->reason->code] : null,
                    'notes' => $i->notes,
                ]),
                'nfs' => $stockAdjustment->nfs,
                'status_history' => $stockAdjustment->statusHistory->map(fn ($h) => [
                    'id' => $h->id,
                    'old_status' => $h->old_status,
                    'old_status_label' => StockAdjustment::STATUS_LABELS[$h->old_status] ?? $h->old_status,
                    'new_status' => $h->new_status,
                    'new_status_label' => StockAdjustment::STATUS_LABELS[$h->new_status] ?? $h->new_status,
                    'changed_by' => $h->changedBy?->name,
                    'notes' => $h->notes,
                    'created_at' => $h->created_at?->format('d/m/Y H:i'),
                ]),
                'attachments' => $stockAdjustment->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'original_filename' => $a->original_filename,
                    'mime_type' => $a->mime_type,
                    'size_human' => $a->size_human,
                    'uploaded_by' => $a->uploadedBy?->name,
                    'created_at' => $a->created_at?->format('d/m/Y H:i'),
                ]),
                'allowed_transitions' => $this->transitionService->allowedTransitions($stockAdjustment, $user),
            ],
        ]);
    }

    public function update(UpdateStockAdjustmentRequest $request, StockAdjustment $stockAdjustment)
    {
        $this->authorizeStoreAccess($stockAdjustment, $request->user());

        try {
            $this->service->update($stockAdjustment, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-adjustments.index')
            ->with('success', 'Ajuste de estoque atualizado com sucesso.');
    }

    public function destroy(StockAdjustment $stockAdjustment, Request $request)
    {
        $this->authorizeStoreAccess($stockAdjustment, $request->user());

        try {
            $this->service->delete($stockAdjustment, $request->user(), $request->input('reason'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('stock-adjustments.index')
            ->with('success', 'Ajuste de estoque excluído com sucesso.');
    }

    public function transition(TransitionStockAdjustmentRequest $request, StockAdjustment $stockAdjustment)
    {
        $this->authorizeStoreAccess($stockAdjustment, $request->user());

        try {
            $this->transitionService->executeTransition(
                $stockAdjustment,
                $request->validated('new_status'),
                $request->user(),
                $request->validated('notes'),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $label = StockAdjustment::STATUS_LABELS[$request->validated('new_status')] ?? $request->validated('new_status');

        return redirect()->back()->with('success', "Status atualizado para: {$label}.");
    }

    public function bulkTransition(BulkTransitionRequest $request)
    {
        $result = $this->transitionService->bulkTransition(
            $request->validated('ids'),
            $request->validated('new_status'),
            $request->user(),
            $request->validated('notes'),
        );

        $msg = "{$result['success']} ajuste(s) atualizado(s).";
        if ($result['failed'] > 0) {
            $msg .= " {$result['failed']} falharam.";
        }

        return redirect()->back()->with('success', $msg);
    }

    // ===== Lookups (autocomplete) =====

    /**
     * Lista colaboradores ativos da loja selecionada.
     * Usado pelo form de criação para filtrar a consultora após a loja ser escolhida.
     */
    public function employeesByStore(Request $request)
    {
        $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        $store = Store::findOrFail($request->store_id);

        $employees = Employee::query()
            ->active()
            ->byStore($store->code)
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'store_id']);

        return response()->json([
            'employees' => $employees->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'short_name' => $e->short_name,
            ]),
        ]);
    }

    /**
     * Autocomplete de produtos por referência/descrição.
     * Retorna até 20 itens.
     */
    public function searchProducts(Request $request)
    {
        $term = trim((string) $request->query('term', ''));
        if (strlen($term) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->active()
            ->search($term)
            ->orderBy('reference')
            ->limit(20)
            ->get(['id', 'reference', 'description', 'image']);

        return response()->json([
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'reference' => $p->reference,
                'description' => $p->description,
                'image' => $p->image,
            ]),
        ]);
    }

    /**
     * Retorna detalhes do produto e seus tamanhos disponíveis (variantes ativas).
     * Saldo por tamanho não é gerenciado em tenant — exibido como null e preenchido
     * manualmente pelo usuário no form.
     */
    public function productSizes(Request $request, string $reference)
    {
        $product = Product::query()
            ->active()
            ->with(['variants' => fn ($q) => $q->where('is_active', true), 'variants.size:cigam_code,name'])
            ->where('reference', $reference)
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        // Coleta tamanhos distintos a partir das variantes ativas.
        $sizes = $product->variants
            ->map(fn ($v) => [
                'size_code' => $v->size_cigam_code,
                'size' => $v->size?->name ?? $v->size_cigam_code,
                'barcode' => $v->barcode,
                'stock' => null, // não rastreado neste módulo; usuário informa manualmente
            ])
            ->unique('size')
            ->values();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'reference' => $product->reference,
                'description' => $product->description,
                'image' => $product->image,
                'is_single_size' => $sizes->count() <= 1,
                'sizes' => $sizes,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $filename = 'ajustes-de-estoque-'.now()->format('Y-m-d_His').'.xlsx';

        return (new StockAdjustmentsExport(
            $request->user(),
            $request->only(['status', 'store_id', 'reason_id', 'direction', 'date_from', 'date_to']),
        ))->download($filename);
    }

    // ===== NFs =====

    public function storeNf(StoreStockAdjustmentNfRequest $request, StockAdjustment $stockAdjustment)
    {
        $this->authorizeStoreAccess($stockAdjustment, $request->user());

        $this->nfService->store($stockAdjustment, $request->validated(), $request->user());

        return redirect()->back()->with('success', 'NF registrada com sucesso.');
    }

    public function destroyNf(Request $request, StockAdjustment $stockAdjustment, StockAdjustmentItemNf $nf)
    {
        $this->authorizeStoreAccess($stockAdjustment, $request->user());
        abort_unless($nf->stock_adjustment_id === $stockAdjustment->id, 404);

        $this->nfService->delete($nf);

        return redirect()->back()->with('success', 'NF removida.');
    }

    // ===== Attachments =====

    public function storeAttachment(UploadAttachmentRequest $request, StockAdjustment $stockAdjustment)
    {
        $this->authorizeStoreAccess($stockAdjustment, $request->user());

        $this->service->addAttachment($stockAdjustment, $request->file('file'), $request->user());

        return redirect()->back()->with('success', 'Anexo enviado com sucesso.');
    }

    public function downloadAttachment(Request $request, StockAdjustment $stockAdjustment, StockAdjustmentAttachment $attachment): StreamedResponse
    {
        $this->authorizeStoreAccess($stockAdjustment, $request->user());
        abort_unless($attachment->stock_adjustment_id === $stockAdjustment->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_filename);
    }

    public function destroyAttachment(Request $request, StockAdjustment $stockAdjustment, StockAdjustmentAttachment $attachment)
    {
        $this->authorizeStoreAccess($stockAdjustment, $request->user());
        abort_unless($attachment->stock_adjustment_id === $stockAdjustment->id, 404);

        $this->service->removeAttachment($attachment);

        return redirect()->back()->with('success', 'Anexo removido.');
    }

    // ===== Helpers =====

    private function applyStoreScope($query, $user): void
    {
        if ($this->isAdmin($user)) {
            return;
        }

        // Usuário comum: restringir à sua loja via códgo (users.store_id refere-se a stores.code).
        if (! empty($user->store_id)) {
            $query->whereHas('store', fn ($q) => $q->where('code', $user->store_id));
        }
    }

    private function authorizeStoreAccess(StockAdjustment $adjustment, $user): void
    {
        if ($this->isAdmin($user)) {
            return;
        }
        if (empty($user->store_id)) {
            abort(403);
        }
        $adjustment->loadMissing('store');
        if ($adjustment->store?->code !== $user->store_id) {
            abort(403);
        }
    }

    private function isAdmin($user): bool
    {
        $role = $user->role?->value ?? null;

        return in_array($role, [Role::SUPER_ADMIN->value, Role::ADMIN->value, Role::SUPPORT->value], true);
    }
}
