<?php

namespace App\Http\Controllers;

use App\Exports\RejectedPriceRowsExport;
use App\Imports\ProductPricesImport;
use App\Models\Product;
use App\Models\ProductArticleComplement;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductCollection;
use App\Models\ProductColor;
use App\Models\ProductMaterial;
use App\Models\ProductSize;
use App\Models\ProductSubcollection;
use App\Models\ProductSupplier;
use App\Models\ProductSyncLog;
use App\Models\ProductVariant;
use App\Services\EanGeneratorService;
use App\Services\ImageUploadService;
use App\Services\LabelPrintService;
use App\Services\ProductSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

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

        // Auto-fail syncs stuck running for over 30 minutes (orphaned processes)
        ProductSyncLog::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(30))
            ->each(fn ($log) => $log->markFailed('Processo encerrado automaticamente após 30 minutos sem resposta.'));

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

        $data = $product->toArray();
        // tenant_asset() (não asset('storage/...')): imagens de produto são
        // salvas no disk='public' DENTRO do contexto tenant — em
        // storage/tenant{id}/app/public/products/. asset_helper_tenancy
        // reescreve asset('storage/foo') → /tenancy/assets/storage/foo
        // mantendo storage/ literal no path → 404 no TenantAssetsController.
        // tenant_asset(foo) gera /tenancy/assets/foo direto (200 OK).
        $data['image_url'] = $product->image ? tenant_asset($product->image) : null;
        $data['markup'] = ($product->cost_price && $product->cost_price > 0)
            ? round((($product->sale_price - $product->cost_price) / $product->cost_price) * 100, 1)
            : null;

        return response()->json($data);
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

        $ean = (new EanGeneratorService)->generate($product->id, $variant->id);
        $variant->update(['aux_reference' => $ean]);

        return response()->json(['message' => 'EAN-13 gerado.', 'ean' => $ean, 'variant' => $variant->fresh()]);
    }

    public function syncInit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:full,incremental,lookups_only,prices_only,by_period',
            'date_start' => 'required_if:type,by_period|nullable|date',
            'date_end' => 'required_if:type,by_period|nullable|date|after_or_equal:date_start',
        ]);

        $syncService = app(ProductSyncService::class);

        if (! $syncService->isAvailable()) {
            return response()->json(['error' => 'Conexão CIGAM não disponível.'], 503);
        }

        // Auto-fail orphaned syncs (running > 30 min)
        ProductSyncLog::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(30))
            ->each(fn ($log) => $log->markFailed('Processo encerrado automaticamente após 30 minutos sem resposta.'));

        // Reject if a sync is already running
        $running = ProductSyncLog::where('status', 'running')->first();
        if ($running) {
            return response()->json([
                'error' => 'Já existe uma sincronização em andamento.',
                'log' => $running,
            ], 409);
        }

        // For lookups_only, spawn background process (same as other types)
        if ($validated['type'] === 'lookups_only') {
            $log = ProductSyncLog::create([
                'sync_type' => 'lookups_only',
                'status' => 'running',
                'current_phase' => 'lookups',
                'total_records' => 0,
                'started_at' => now(),
                'started_by_user_id' => $request->user()->id,
            ]);

            $this->spawnSyncProcess($log->id, 'lookups_only', $request->user()->id);

            return response()->json(['log' => $log]);
        }

        // Spawn background process with the PHP that has pdo_pgsql
        $dateStart = $validated['date_start'] ?? null;
        $dateEnd = $validated['date_end'] ?? null;
        $type = $validated['type'];

        $log = $syncService->initSync($type, $request->user()->id, $dateStart, $dateEnd);

        $this->spawnSyncProcess($log->id, $type, $request->user()->id, $dateStart, $dateEnd);

        return response()->json(['log' => $log]);
    }

    /**
     * Sync lookup tables as a separate request (called before chunk processing).
     */
    public function syncLookupsEndpoint(Request $request): JsonResponse
    {
        $syncService = app(ProductSyncService::class);

        if (! $syncService->isAvailable()) {
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
            'lookup_total' => $log->lookup_total,
            'lookup_processed' => $log->lookup_processed,
            'lookup_current' => $log->lookup_current,
            'price_total' => $log->price_total,
            'price_processed' => $log->price_processed,
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
            'suppliers' => ProductSupplier::active()->orderBy('razao_social')->get(['codigo_for', 'razao_social', 'nome_fantasia']),
        ]);
    }

    // =============================================
    // PRODUCT IMAGE
    // =============================================

    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        try {
            $imageService = app(ImageUploadService::class);

            $path = $imageService->uploadImage(
                $request->file('image'),
                'products',
                $product->image
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $product->update([
            'image' => $path,
            'updated_by_user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Imagem atualizada.',
            'image' => $path,
            'image_url' => tenant_asset($path),
        ]);
    }

    public function deleteImage(Product $product): JsonResponse
    {
        if ($product->image) {
            $imageService = app(ImageUploadService::class);
            $imageService->deleteFile($product->image);

            $product->update([
                'image' => null,
                'updated_by_user_id' => auth()->id(),
            ]);
        }

        return response()->json(['message' => 'Imagem removida.']);
    }

    /**
     * Spawn products:sync as a background process using the PHP binary with pdo_pgsql.
     */
    protected function spawnSyncProcess(int $logId, string $type, int $userId, ?string $dateStart = null, ?string $dateEnd = null): void
    {
        $php = config('app.php_binary', 'C:\\Users\\MSDEV\\php84\\php.exe');
        $artisan = base_path('artisan');
        $tenantId = tenant('id');

        $args = [
            $php, $artisan, 'products:sync', $type,
            '--tenant='.$tenantId,
            '--log-id='.$logId,
            '--user-id='.$userId,
        ];

        if ($dateStart) {
            $args[] = '--date-start='.$dateStart;
        }
        if ($dateEnd) {
            $args[] = '--date-end='.$dateEnd;
        }

        $cmd = implode(' ', array_map('escapeshellarg', $args));

        // Windows: start /B with empty title ("") to avoid first arg being treated as title
        pclose(popen("start /B \"\" {$cmd}", 'r'));
    }

    // =============================================
    // PRICE IMPORT
    // =============================================

    public function importPrices(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        set_time_limit(0);

        $import = new ProductPricesImport($request->user()->id);
        Excel::import($import, $request->file('file'));

        $results = $import->getResults();

        // If there are rejected rows, save as CSV for download
        $rejectedUrl = null;
        if (! empty($results['rejected_rows'])) {
            $filename = 'precos_rejeitados_'.now()->format('Y-m-d_His').'.xlsx';
            Excel::store(new RejectedPriceRowsExport($results['rejected_rows']), "temp/{$filename}", 'local');
            $rejectedUrl = "/products/import-prices/rejected/{$filename}";
        }

        return response()->json([
            'success' => $results['success'],
            'unchanged' => $results['unchanged'],
            'skipped_locked' => $results['skipped_locked'],
            'not_found' => $results['not_found'],
            'errors' => $results['errors'],
            'error_count' => count($results['errors']),
            'rejected_count' => count($results['rejected_rows']),
            'rejected_url' => $rejectedUrl,
        ]);
    }

    public function downloadRejected(string $filename)
    {
        $path = storage_path("app/temp/{$filename}");

        if (! file_exists($path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return response()->download($path)->deleteFileAfterSend(true);
    }

    // =============================================
    // LABEL PRINTING
    // =============================================

    public function printLabels(Request $request)
    {
        $request->validate([
            'variant_ids' => 'required|array|min:1',
            'variant_ids.*' => 'exists:product_variants,id',
            'preset' => 'required|array',
            'preset.width' => 'required|numeric|min:20|max:200',
            'preset.height' => 'required|numeric|min:10|max:200',
            'preset.columns' => 'required|integer|min:1|max:6',
            'preset.gap' => 'required|numeric|min:0|max:20',
            'preset.format' => 'required|string|in:A4,custom',
        ]);

        $service = new LabelPrintService;
        $pdf = $service->generatePdf($request->variant_ids, $request->preset);

        return $pdf->stream('etiquetas.pdf');
    }

    public function searchVariants(Request $request): JsonResponse
    {
        $search = $request->get('search', '');

        $products = Product::with(['variants' => fn ($q) => $q->active()->with('size')])
            ->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->active()
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'reference' => $p->reference,
                'description' => $p->description,
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'size_name' => $v->size?->name ?? $v->size_cigam_code,
                    'barcode' => $v->barcode,
                    'aux_reference' => $v->aux_reference,
                ]),
            ]);

        return response()->json($products);
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
