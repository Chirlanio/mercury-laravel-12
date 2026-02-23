<?php

namespace App\Http\Controllers;

use App\Jobs\ProductSyncJob;
use App\Models\Product;
use App\Models\ProductArticleComplement;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductCollection;
use App\Models\ProductColor;
use App\Models\ProductMaterial;
use App\Models\ProductSize;
use App\Models\ProductSubcollection;
use App\Models\ProductSyncLog;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\EanGeneratorService;
use App\Services\ProductSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['brand', 'collection', 'category', 'color', 'material', 'supplier'])
            ->withCount('variants');

        if ($search = $request->input('search')) {
            $query->search($search);
        }

        if ($brand = $request->input('brand')) {
            $query->where('brand_cigam_code', $brand);
        }

        if ($collection = $request->input('collection')) {
            $query->where('collection_cigam_code', $collection);
        }

        if ($category = $request->input('category')) {
            $query->where('category_cigam_code', $category);
        }

        if ($color = $request->input('color')) {
            $query->where('color_cigam_code', $color);
        }

        if ($material = $request->input('material')) {
            $query->where('material_cigam_code', $material);
        }

        if ($supplier = $request->input('supplier')) {
            $query->where('supplier_codigo_for', $supplier);
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('sync_locked') && $request->input('sync_locked') !== '') {
            $query->where('sync_locked', $request->boolean('sync_locked'));
        }

        $sortField = $request->input('sort', 'reference');
        $sortDirection = $request->input('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $products = $query->paginate(50)->withQueryString();

        $stats = [
            'total' => Product::count(),
            'active' => Product::where('is_active', true)->count(),
            'sync_locked' => Product::where('sync_locked', true)->count(),
            'last_sync' => ProductSyncLog::where('status', 'completed')
                ->latest('completed_at')
                ->value('completed_at'),
        ];

        $syncService = app(ProductSyncService::class);

        $activeSyncLog = ProductSyncLog::where('status', 'running')
            ->latest('started_at')
            ->first();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'brand', 'collection', 'category', 'color', 'material', 'supplier', 'is_active', 'sync_locked', 'sort', 'direction']),
            'stats' => $stats,
            'cigamAvailable' => $syncService->isAvailable(),
            'activeSyncLog' => $activeSyncLog,
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load([
            'brand', 'collection', 'subcollection', 'category',
            'color', 'material', 'articleComplement', 'supplier',
            'variants.size', 'createdBy', 'updatedBy',
        ]);

        return response()->json($product);
    }

    public function edit(Product $product): JsonResponse
    {
        $product->load(['variants.size']);

        return response()->json([
            'product' => $product,
            'options' => $this->getEditOptions(),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'required|string|max:500',
            'sale_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'brand_cigam_code' => 'nullable|string',
            'collection_cigam_code' => 'nullable|string',
            'subcollection_cigam_code' => 'nullable|string',
            'category_cigam_code' => 'nullable|string',
            'color_cigam_code' => 'nullable|string',
            'material_cigam_code' => 'nullable|string',
            'article_complement_cigam_code' => 'nullable|string',
        ]);

        $validated['sync_locked'] = true;
        $validated['updated_by_user_id'] = $request->user()->id;

        $product->update($validated);

        return response()->json(['message' => 'Produto atualizado com sucesso.', 'product' => $product->fresh()]);
    }

    public function unlockSync(Product $product): JsonResponse
    {
        $product->update(['sync_locked' => false]);

        return response()->json(['message' => 'Produto desbloqueado para sincronização.']);
    }

    public function updateVariant(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        if ($variant->product_id !== $product->id) {
            abort(404);
        }

        $validated = $request->validate([
            'barcode' => 'nullable|string|max:50',
            'size_cigam_code' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $variant->update($validated);

        return response()->json(['message' => 'Variante atualizada.', 'variant' => $variant->fresh()]);
    }

    public function storeVariant(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => 'nullable|string|max:50',
            'size_cigam_code' => 'nullable|string',
        ]);

        $variant = $product->variants()->create($validated);

        return response()->json(['message' => 'Variante criada.', 'variant' => $variant], 201);
    }

    public function generateEan(Product $product, ProductVariant $variant): JsonResponse
    {
        if ($variant->product_id !== $product->id) {
            abort(404);
        }

        $ean = (new EanGeneratorService())->generate($product->id, $variant->id);
        $variant->update(['aux_reference' => $ean]);

        return response()->json(['message' => 'EAN-13 gerado.', 'ean' => $ean, 'variant' => $variant->fresh()]);
    }

    public function syncInit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:full,incremental,lookups_only,prices_only,by_period',
        ]);

        $syncService = app(ProductSyncService::class);

        if (!$syncService->isAvailable()) {
            return response()->json(['error' => 'Conexão CIGAM não disponível.'], 503);
        }

        // Reject if a sync is already running
        $running = ProductSyncLog::where('status', 'running')->first();
        if ($running) {
            return response()->json([
                'error' => 'Já existe uma sincronização em andamento.',
                'log' => $running,
            ], 409);
        }

        // For lookups_only, sync them inline and return immediately (fast)
        if ($validated['type'] === 'lookups_only') {
            $log = ProductSyncLog::create([
                'sync_type' => 'lookups_only',
                'status' => 'running',
                'current_phase' => 'lookups',
                'started_at' => now(),
                'started_by_user_id' => $request->user()->id,
            ]);

            $results = $syncService->syncLookups();
            $log->markCompleted();

            return response()->json(['log' => $log->fresh(), 'lookups' => $results]);
        }

        // For other types: create log + count total, then dispatch job
        $log = $syncService->initSync($validated['type'], $request->user()->id);

        ProductSyncJob::dispatch($log->id, $validated['type']);

        return response()->json(['log' => $log]);
    }

    /**
     * Sync lookup tables as a separate request (called before chunk processing).
     */
    public function syncLookupsEndpoint(Request $request): JsonResponse
    {
        $syncService = app(ProductSyncService::class);

        if (!$syncService->isAvailable()) {
            return response()->json(['error' => 'Conexão CIGAM não disponível.'], 503);
        }

        $results = $syncService->syncLookups();

        return response()->json(['lookups' => $results]);
    }

    public function syncStatus(ProductSyncLog $log): JsonResponse
    {
        return response()->json([
            'id' => $log->id,
            'status' => $log->status,
            'current_phase' => $log->current_phase,
            'total_records' => $log->total_records,
            'processed_records' => $log->processed_records,
            'inserted_records' => $log->inserted_records,
            'updated_records' => $log->updated_records,
            'skipped_records' => $log->skipped_records,
            'error_count' => $log->error_count,
            'error_details' => $log->error_details,
            'started_at' => $log->started_at,
            'completed_at' => $log->completed_at,
        ]);
    }

    public function syncChunk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'log_id' => 'required|integer|exists:product_sync_logs,id',
            'last_reference' => 'nullable|string',
            'size' => 'integer|min:50|max:2000',
        ]);

        $syncService = app(ProductSyncService::class);
        $result = $syncService->processChunk(
            $validated['log_id'],
            $validated['last_reference'] ?? null,
            $validated['size'] ?? 500
        );

        return response()->json($result);
    }

    public function syncPrices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'log_id' => 'required|integer|exists:product_sync_logs,id',
        ]);

        $syncService = app(ProductSyncService::class);
        $result = $syncService->syncPrices($validated['log_id']);

        return response()->json($result);
    }

    public function syncFinalize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'log_id' => 'required|integer|exists:product_sync_logs,id',
        ]);

        $syncService = app(ProductSyncService::class);
        $log = $syncService->finalizeSync($validated['log_id']);

        return response()->json(['log' => $log]);
    }

    public function syncCancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'log_id' => 'required|integer|exists:product_sync_logs,id',
        ]);

        $syncService = app(ProductSyncService::class);
        $log = $syncService->cancelSync($validated['log_id']);

        return response()->json(['log' => $log]);
    }

    public function syncLogs(): JsonResponse
    {
        $logs = ProductSyncLog::with('startedBy')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($logs);
    }

    public function filterOptions(): JsonResponse
    {
        return response()->json([
            'brands' => ProductBrand::active()->orderBy('name')->get(['cigam_code', 'name']),
            'collections' => ProductCollection::active()->orderBy('name')->get(['cigam_code', 'name']),
            'categories' => ProductCategory::active()->orderBy('name')->get(['cigam_code', 'name']),
            'colors' => ProductColor::active()->orderBy('name')->get(['cigam_code', 'name']),
            'materials' => ProductMaterial::active()->orderBy('name')->get(['cigam_code', 'name']),
            'suppliers' => Supplier::active()->orderBy('razao_social')->get(['codigo_for', 'razao_social', 'nome_fantasia']),
        ]);
    }

    private function getEditOptions(): array
    {
        return [
            'brands' => ProductBrand::active()->orderBy('name')->get(['cigam_code', 'name']),
            'collections' => ProductCollection::active()->orderBy('name')->get(['cigam_code', 'name']),
            'subcollections' => ProductSubcollection::active()->orderBy('name')->get(['cigam_code', 'name']),
            'categories' => ProductCategory::active()->orderBy('name')->get(['cigam_code', 'name']),
            'colors' => ProductColor::active()->orderBy('name')->get(['cigam_code', 'name']),
            'materials' => ProductMaterial::active()->orderBy('name')->get(['cigam_code', 'name']),
            'article_complements' => ProductArticleComplement::active()->orderBy('name')->get(['cigam_code', 'name']),
            'sizes' => ProductSize::active()->orderBy('name')->get(['cigam_code', 'name']),
        ];
    }
}
