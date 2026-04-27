<?php

namespace App\Http\Controllers;

use App\Enums\DamagedProductStatus;
use App\Enums\DamageMatchStatus;
use App\Enums\DamageMatchType;
use App\Enums\FootSide;
use App\Enums\Permission;
use App\Http\Requests\DamagedProduct\AcceptMatchRequest;
use App\Http\Requests\DamagedProduct\RejectMatchRequest;
use App\Http\Requests\DamagedProduct\StoreDamagedProductRequest;
use App\Http\Requests\DamagedProduct\UpdateDamagedProductRequest;
use App\Models\DamagedProduct;
use App\Models\DamagedProductMatch;
use App\Models\DamageType;
use App\Models\Network;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\Store;
use App\Models\User;
use App\Services\DamagedProductExportService;
use App\Services\DamagedProductImportService;
use App\Services\DamagedProductMatchingService;
use App\Services\DamagedProductService;
use App\Services\DamagedProductTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DamagedProductController extends Controller
{
    public function __construct(
        private DamagedProductService $service,
        private DamagedProductTransitionService $transitions,
        private DamagedProductMatchingService $matching,
        private DamagedProductExportService $exportService,
        private DamagedProductImportService $importService,
    ) {}

    // ==================================================================
    // Listing + filtros
    // ==================================================================

    public function index(Request $request): Response
    {
        $user = $request->user();
        $scopedStoreId = $this->resolveScopedStoreId($user);

        $query = DamagedProduct::with([
            'store:id,code,name',
            'damageType:id,name',
            'createdBy:id,name',
        ])
            ->withCount(['matchesAsA as pending_matches_count_a' => fn ($q) => $q->where('status', DamageMatchStatus::PENDING->value)])
            ->withCount(['matchesAsB as pending_matches_count_b' => fn ($q) => $q->where('status', DamageMatchStatus::PENDING->value)])
            ->latest();

        if ($scopedStoreId) {
            $query->where('store_id', $scopedStoreId);
        } elseif ($request->filled('store_id')) {
            $query->where('store_id', $request->integer('store_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } elseif (! $request->boolean('include_terminal')) {
            $query->notFinal();
        }

        if ($request->filled('issue_type')) {
            $issueType = $request->string('issue_type')->toString();
            if ($issueType === 'mismatched') {
                $query->where('is_mismatched', true);
            } elseif ($issueType === 'damaged') {
                $query->where('is_damaged', true);
            }
        }

        if ($request->filled('damage_type_id')) {
            $query->where('damage_type_id', $request->integer('damage_type_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('product_reference', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhere('product_color', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        $items = $query->paginate(15)
            ->withQueryString()
            ->through(fn ($p) => $this->formatItem($p));

        return Inertia::render('DamagedProducts/Index', [
            'items' => $items,
            'filters' => $request->only([
                'store_id', 'status', 'issue_type', 'damage_type_id',
                'search', 'date_from', 'date_to', 'include_terminal',
            ]),
            'statistics' => $this->buildStatistics($scopedStoreId),
            'statusOptions' => DamagedProductStatus::options(),
            'isStoreScoped' => $scopedStoreId !== null,
            'scopedStoreId' => $scopedStoreId,
            'permissions' => $this->permissionsPayload($user),
            'selects' => [
                'stores' => $scopedStoreId
                    ? Store::where('id', $scopedStoreId)->get(['id', 'code', 'name'])
                    : Store::orderBy('name')->get(['id', 'code', 'name']),
                'damageTypes' => DamageType::active()->orderBy('sort_order')->get(['id', 'name']),
                'footOptions' => collect(FootSide::cases())->map(fn ($f) => [
                    'value' => $f->value,
                    'label' => $f->label(),
                    'is_single' => $f->isSingleFoot(),
                ])->values(),
            ],
        ]);
    }

    // ==================================================================
    // CRUD
    // ==================================================================

    public function store(StoreDamagedProductRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $this->ensureCanCreateForStore($user, (int) $data['store_id']);

        try {
            $product = $this->service->create(
                $data,
                $user,
                $request->file('photos') ?: null,
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        // Roda matching pós-create (síncrono — geralmente <50ms por candidato)
        $this->matching->findMatchesFor($product);

        return redirect()->route('damaged-products.index')
            ->with('success', 'Produto avariado cadastrado.');
    }

    public function show(DamagedProduct $damagedProduct, Request $request): JsonResponse
    {
        $this->ensureCanView($request->user(), $damagedProduct);

        $damagedProduct->load([
            'store:id,code,name',
            'damageType:id,name',
            'photos',
            'product:id,reference,image',
            'product.variants:id,product_id,size_cigam_code,is_active',
            'product.variants.size:cigam_code,name',
            'createdBy:id,name',
            'updatedBy:id,name',
            'cancelledBy:id,name',
            'statusHistory.actor:id,name',
            'statusHistory.triggeredByMatch:id,match_type',
        ]);

        return response()->json([
            'item' => $this->formatItem($damagedProduct, full: true),
            'history' => $damagedProduct->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'note' => $h->note,
                'actor' => $h->actor?->name ?? 'Sistema',
                'triggered_by_match_id' => $h->triggered_by_match_id,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function update(UpdateDamagedProductRequest $request, DamagedProduct $damagedProduct): RedirectResponse
    {
        $user = $request->user();
        $this->ensureCanEdit($user, $damagedProduct);

        $data = $request->validated();

        try {
            $updated = $this->service->update(
                $damagedProduct,
                $data,
                $user,
                $request->file('photos') ?: null,
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        // Re-roda matching se mudaram campos relevantes (foot/sizes/foot_damaged)
        if ($this->matchingFieldsChanged($damagedProduct, $data)) {
            $this->matching->findMatchesFor($updated);
        }

        return redirect()->route('damaged-products.index')
            ->with('success', 'Produto avariado atualizado.');
    }

    public function destroy(DamagedProduct $damagedProduct, Request $request): RedirectResponse
    {
        $user = $request->user();
        $reason = $request->string('reason')->toString();

        if ($reason === '') {
            return redirect()->back()->withErrors(['reason' => 'Informe o motivo do cancelamento.']);
        }

        try {
            $this->transitions->transition(
                $damagedProduct,
                DamagedProductStatus::CANCELLED,
                $user,
                $reason,
            );

            $this->service->expirePendingMatches($damagedProduct);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->route('damaged-products.index')
            ->with('success', 'Produto avariado cancelado.');
    }

    // ==================================================================
    // Matches (lazy load + ações)
    // ==================================================================

    public function loadMatches(DamagedProduct $damagedProduct, Request $request): JsonResponse
    {
        $this->ensureCanView($request->user(), $damagedProduct);

        $matches = DamagedProductMatch::with([
            'productA.store:id,code,name',
            'productB.store:id,code,name',
            'suggestedOriginStore:id,code,name',
            'suggestedDestinationStore:id,code,name',
            'transfer:id,invoice_number,status',
            'respondedBy:id,name',
        ])
            ->forProduct($damagedProduct->id)
            ->latest('id')
            ->get();

        return response()->json([
            'matches' => $matches->map(fn ($m) => $this->formatMatch($m, $damagedProduct)),
        ]);
    }

    public function runMatching(Request $request): JsonResponse
    {
        $request->user(); // garante middleware de auth

        $stats = $this->matching->runFullMatching();

        return response()->json([
            'message' => "Matching concluído — {$stats['scanned']} produtos varridos, {$stats['matches_created']} novos matches.",
            'stats' => $stats,
        ]);
    }

    public function acceptMatch(AcceptMatchRequest $request, DamagedProductMatch $match): RedirectResponse
    {
        try {
            $this->matching->acceptMatch($match, $request->user(), $request->validated('invoice_number'));
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Match aceito — transferência criada.');
    }

    public function rejectMatch(RejectMatchRequest $request, DamagedProductMatch $match): RedirectResponse
    {
        try {
            $this->matching->rejectMatch($match, $request->user(), $request->validated('reason'));
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Match rejeitado.');
    }

    public function resolveMatch(DamagedProductMatch $match, Request $request): RedirectResponse
    {
        try {
            $this->matching->resolveMatch($match, $request->user(), $request->string('note')->toString() ?: null);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Match resolvido.');
    }

    // ==================================================================
    // Lookups (autocomplete)
    // ==================================================================

    /**
     * Autocomplete de produto por reference (digitação no modal de criar).
     * Retorna até 10 sugestões com brand_name/color_name resolvidos pra
     * auto-fill — UI mostra NOMES, não cigam_codes.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $term = $request->string('q')->toString();

        if (mb_strlen($term) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->select(['id', 'reference', 'description', 'brand_cigam_code', 'color_cigam_code'])
            ->with(['brand:cigam_code,name', 'color:cigam_code,name'])
            ->where(function ($q) use ($term) {
                $q->where('reference', 'like', "{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            })
            ->where('is_active', true)
            ->limit(10)
            ->get();

        return response()->json([
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'reference' => $p->reference,
                'description' => $p->description,
                'brand_cigam_code' => $p->brand_cigam_code,
                'brand_name' => $p->brand?->name,
                'color_cigam_code' => $p->color_cigam_code,
                'color_name' => $p->color?->name,
            ]),
        ]);
    }

    /**
     * Lista os tamanhos disponíveis para um produto (via product_variants).
     * Usado pelo card "Detalhes do par trocado" pra renderizar 2 grids
     * clicáveis (Esquerdo + Direito).
     */
    public function productSizes(Product $product): JsonResponse
    {
        $sizes = $product->variants()
            ->with('size:cigam_code,name')
            ->where('is_active', true)
            ->get()
            ->map(fn ($v) => [
                'cigam_code' => $v->size_cigam_code,
                'name' => $v->size?->name ?? $v->size_cigam_code,
            ])
            ->unique('cigam_code')
            ->sortBy(fn ($s) => is_numeric($s['name']) ? (int) $s['name'] : $s['name'])
            ->values();

        return response()->json(['sizes' => $sizes]);
    }

    /**
     * Stats em JSON pra refresh do StatisticsGrid sem page reload.
     */
    public function statistics(Request $request): JsonResponse
    {
        $scopedStoreId = $this->resolveScopedStoreId($request->user());

        return response()->json($this->buildStatistics($scopedStoreId));
    }

    // ==================================================================
    // Export / Import
    // ==================================================================

    /**
     * Export XLSX (2 abas: Registros + Matches) com os mesmos filtros
     * aplicados na listagem. Respeita scoping por loja.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $scopedStoreId = $this->resolveScopedStoreId($user);

        $query = DamagedProduct::query()->latest();

        if ($scopedStoreId) {
            $query->where('store_id', $scopedStoreId);
        } elseif ($request->filled('store_id')) {
            $query->where('store_id', $request->integer('store_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        } elseif (! $request->boolean('include_terminal')) {
            $query->notFinal();
        }

        if ($request->filled('issue_type')) {
            $issueType = $request->string('issue_type')->toString();
            if ($issueType === 'mismatched') $query->where('is_mismatched', true);
            elseif ($issueType === 'damaged') $query->where('is_damaged', true);
        }

        if ($request->filled('damage_type_id')) {
            $query->where('damage_type_id', $request->integer('damage_type_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('product_reference', 'like', "%{$search}%")
                    ->orWhere('product_name', 'like', "%{$search}%")
                    ->orWhere('product_color', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        return $this->exportService->exportExcel($query);
    }

    /**
     * Gera o laudo PDF individual de um produto avariado.
     */
    public function exportPdf(DamagedProduct $damagedProduct, Request $request): HttpResponse
    {
        $this->ensureCanView($request->user(), $damagedProduct);

        return $this->exportService->exportPdf($damagedProduct);
    }

    /**
     * Importa produtos avariados via XLSX (migração v1).
     * Retorna sumário com contagem de imported + lista de erros por linha.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB
        ]);

        $result = $this->importService->importFromUploadedFile(
            $request->file('file'),
            $request->user(),
        );

        $message = "Importação concluída: {$result['imported']} registros importados.";
        if (! empty($result['errors'])) {
            $message .= ' ' . count($result['errors']) . ' linha(s) com erro.';
        }

        return redirect()->route('damaged-products.index')
            ->with('success', $message)
            ->with('import_errors', $result['errors']);
    }

    // ==================================================================
    // Authorization helpers
    // ==================================================================

    /**
     * Resolve a `stores.id` (numérica) da loja do usuário, dado que
     * users.store_id armazena o `code` (string como Z441) — relação no
     * User::store() usa code como FK. Sem loja ou com MANAGE: null
     * (visão global).
     */
    protected function resolveScopedStoreId(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        if ($user->hasPermissionTo(Permission::MANAGE_DAMAGED_PRODUCTS->value)) {
            return null;
        }

        $code = $user->store_id ?: null;
        if (! $code) {
            return null;
        }

        return Store::where('code', $code)->value('id');
    }

    protected function ensureCanView(?User $user, DamagedProduct $product): void
    {
        $scoped = $this->resolveScopedStoreId($user);
        if ($scoped !== null && $product->store_id !== $scoped) {
            abort(403, 'Você não tem acesso a registros de outras lojas.');
        }
    }

    protected function ensureCanEdit(?User $user, DamagedProduct $product): void
    {
        $this->ensureCanView($user, $product);

        if ($product->isFinal() && ! $user?->hasPermissionTo(Permission::MANAGE_DAMAGED_PRODUCTS->value)) {
            abort(403, 'Registros em estado final só podem ser editados pelo admin.');
        }
    }

    protected function ensureCanCreateForStore(?User $user, int $storeId): void
    {
        $scoped = $this->resolveScopedStoreId($user);
        if ($scoped !== null && $storeId !== $scoped) {
            abort(403, 'Você só pode cadastrar registros para a sua loja.');
        }
    }

    // ==================================================================
    // Statistics + formatters
    // ==================================================================

    protected function buildStatistics(?int $scopedStoreId): array
    {
        $base = DamagedProduct::query();
        if ($scopedStoreId) {
            $base->where('store_id', $scopedStoreId);
        }

        // 1 SELECT com CASE pra somar por status (padrão Movements)
        $row = (clone $base)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as matched_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as transfer_requested_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as resolved_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_count
            ', [
                DamagedProductStatus::OPEN->value,
                DamagedProductStatus::MATCHED->value,
                DamagedProductStatus::TRANSFER_REQUESTED->value,
                DamagedProductStatus::RESOLVED->value,
                DamagedProductStatus::CANCELLED->value,
            ])
            ->first();

        $total = (int) ($row->total ?? 0);
        $resolved = (int) ($row->resolved_count ?? 0);
        $resolutionRate = $total > 0
            ? round(($resolved / $total) * 100, 2)
            : 0.0;

        return [
            'total' => $total,
            'open' => (int) ($row->open_count ?? 0),
            'matched' => (int) ($row->matched_count ?? 0),
            'transfer_requested' => (int) ($row->transfer_requested_count ?? 0),
            'resolved' => $resolved,
            'cancelled' => (int) ($row->cancelled_count ?? 0),
            'resolution_rate' => $resolutionRate,
        ];
    }

    protected function permissionsPayload(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return [
            'view' => $user->hasPermissionTo(Permission::VIEW_DAMAGED_PRODUCTS->value),
            'create' => $user->hasPermissionTo(Permission::CREATE_DAMAGED_PRODUCTS->value),
            'edit' => $user->hasPermissionTo(Permission::EDIT_DAMAGED_PRODUCTS->value),
            'delete' => $user->hasPermissionTo(Permission::DELETE_DAMAGED_PRODUCTS->value),
            'manage' => $user->hasPermissionTo(Permission::MANAGE_DAMAGED_PRODUCTS->value),
            'run_matching' => $user->hasPermissionTo(Permission::RUN_DAMAGED_PRODUCT_MATCHING->value),
            'approve_matches' => $user->hasPermissionTo(Permission::APPROVE_DAMAGED_PRODUCT_MATCHES->value),
            'export' => $user->hasPermissionTo(Permission::EXPORT_DAMAGED_PRODUCTS->value),
        ];
    }

    protected function formatItem(DamagedProduct $p, bool $full = false): array
    {
        $pendingMatches = ($p->pending_matches_count_a ?? 0) + ($p->pending_matches_count_b ?? 0);

        $base = [
            'id' => $p->id,
            'ulid' => $p->ulid,
            'store' => $p->store ? [
                'id' => $p->store->id,
                'code' => $p->store->code,
                'name' => $p->store->name,
            ] : null,
            'product_id' => $p->product_id,
            'product_reference' => $p->product_reference,
            'product_name' => $p->product_name,
            'product_color' => $p->product_color, // armazena NOME (não cigam_code)
            'brand_cigam_code' => $p->brand_cigam_code,
            'brand_name' => $p->brand_name,
            'is_mismatched' => (bool) $p->is_mismatched,
            'is_damaged' => (bool) $p->is_damaged,
            'damage_type' => $p->damageType ? ['id' => $p->damageType->id, 'name' => $p->damageType->name] : null,
            'damaged_foot' => $p->damaged_foot?->value,
            'damaged_foot_label' => $p->damaged_foot?->label(),
            'damaged_size' => $p->damaged_size,
            'mismatched_left_size' => $p->mismatched_left_size,
            'mismatched_right_size' => $p->mismatched_right_size,
            'status' => $p->status?->value,
            'status_label' => $p->status?->label(),
            'status_color' => $p->status?->color(),
            'pending_matches_count' => (int) $pendingMatches,
            'created_at' => $p->created_at?->toIso8601String(),
            'created_by' => $p->createdBy?->name,
        ];

        if ($full) {
            $base['damage_description'] = $p->damage_description;
            $base['is_repairable'] = (bool) $p->is_repairable;
            $base['estimated_repair_cost'] = $p->estimated_repair_cost;
            $base['notes'] = $p->notes;
            $base['cancel_reason'] = $p->cancel_reason;
            $base['cancelled_at'] = $p->cancelled_at?->toIso8601String();
            $base['resolved_at'] = $p->resolved_at?->toIso8601String();
            $base['expires_at'] = $p->expires_at?->toIso8601String();
            $base['product_image_url'] = $p->relationLoaded('product') && $p->product?->image
                ? asset('storage/'.$p->product->image)
                : null;
            // Tamanhos do catálogo (variants) — usados pra renderizar grids
            // de tamanhos no modal de visualização (mesmo padrão do form).
            $base['product_sizes'] = $p->relationLoaded('product') && $p->product
                ? $p->product->variants
                    ->where('is_active', true)
                    ->map(fn ($v) => [
                        'cigam_code' => $v->size_cigam_code,
                        'name' => $v->size?->name ?? $v->size_cigam_code,
                    ])
                    ->unique('cigam_code')
                    ->sortBy(fn ($s) => is_numeric($s['name']) ? (int) $s['name'] : $s['name'])
                    ->values()
                : [];
            $base['photos'] = $p->photos?->map(fn ($photo) => [
                'id' => $photo->id,
                'filename' => $photo->filename,
                'url' => $photo->url,
                'caption' => $photo->caption,
                'sort_order' => $photo->sort_order,
            ])->values();
        }

        return $base;
    }

    protected function formatMatch(DamagedProductMatch $m, DamagedProduct $context): array
    {
        $partner = $m->partnerOf($context);

        return [
            'id' => $m->id,
            'match_type' => $m->match_type->value,
            'match_type_label' => $m->match_type->label(),
            'status' => $m->status->value,
            'status_label' => $m->status->label(),
            'status_color' => $m->status->color(),
            'match_score' => (float) $m->match_score,
            'partner' => $partner ? [
                'id' => $partner->id,
                'ulid' => $partner->ulid,
                'product_reference' => $partner->product_reference,
                'product_name' => $partner->product_name,
                'store' => $partner->store ? ['code' => $partner->store->code, 'name' => $partner->store->name] : null,
                'mismatched_left_size' => $partner->mismatched_left_size,
                'mismatched_right_size' => $partner->mismatched_right_size,
                'damaged_foot' => $partner->damaged_foot?->value,
                'damaged_size' => $partner->damaged_size,
            ] : null,
            'suggested_origin' => $m->suggestedOriginStore ? ['code' => $m->suggestedOriginStore->code, 'name' => $m->suggestedOriginStore->name] : null,
            'suggested_destination' => $m->suggestedDestinationStore ? ['code' => $m->suggestedDestinationStore->code, 'name' => $m->suggestedDestinationStore->name] : null,
            'transfer' => $m->transfer ? [
                'id' => $m->transfer->id,
                'invoice_number' => $m->transfer->invoice_number,
                'status' => $m->transfer->status,
            ] : null,
            'reject_reason' => $m->reject_reason,
            'responded_by' => $m->respondedBy?->name,
            'responded_at' => $m->responded_at?->toIso8601String(),
            'resolved_at' => $m->resolved_at?->toIso8601String(),
        ];
    }

    /**
     * Verifica se algum dos campos que afetam matching foi alterado (pra
     * decidir se vale re-rodar findMatchesFor pós-update).
     */
    protected function matchingFieldsChanged(DamagedProduct $original, array $data): bool
    {
        $relevant = [
            'is_mismatched', 'is_damaged',
            'mismatched_left_size', 'mismatched_right_size',
            'damaged_foot', 'damaged_size', 'product_reference', 'brand_cigam_code',
        ];

        foreach ($relevant as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            if ((string) $original->getOriginal($field) !== (string) $data[$field]) {
                return true;
            }
        }

        return false;
    }
}
