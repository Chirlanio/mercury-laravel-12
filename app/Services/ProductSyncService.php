<?php

namespace App\Services;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductSyncService
{
    public function isAvailable(): bool
    {
        try {
            $config = config('database.connections.cigam');
            if (empty($config['host']) || empty($config['database'])) {
                return false;
            }

            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=3',
                $config['host'],
                $config['port'] ?? '5432',
                $config['database']
            );

            new \PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', [
                \PDO::ATTR_TIMEOUT => 3,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Sync all 8 lookup tables + suppliers from CIGAM.
     * Optionally reports progress to a ProductSyncLog.
     */
    public function syncLookups(?int $logId = null): array
    {
        set_time_limit(300);
        $results = [];
        $log = $logId ? ProductSyncLog::find($logId) : null;

        $lookupMappings = [
            'msl_prod_categoria_' => [
                'model' => ProductCategory::class,
                'code_field' => 'codcategoria',
                'name_field' => 'dsccategoria',
            ],
            'msl_prod_colecao_' => [
                'model' => ProductCollection::class,
                'code_field' => 'codcolecao',
                'name_field' => 'dsccolecao',
                'sanitize_name' => true,
            ],
            'msl_prod_subcolecao_' => [
                'model' => ProductSubcollection::class,
                'code_field' => 'codsubcolecao',
                'name_field' => 'dscsubcolecao',
                'sanitize_name' => true,
            ],
            'msl_prod_cor_' => [
                'model' => ProductColor::class,
                'code_field' => 'codcor',
                'name_field' => 'dsccor',
            ],
            'msl_prod_marca_' => [
                'model' => ProductBrand::class,
                'code_field' => 'codmarca',
                'name_field' => 'dscmarca',
            ],
            'msl_prod_material_' => [
                'model' => ProductMaterial::class,
                'code_field' => 'codmaterial',
                'name_field' => 'dscmaterial',
            ],
            'msl_prod_tamanho_' => [
                'model' => ProductSize::class,
                'code_field' => 'cod_tamanho',
                'name_field' => 'descricao',
            ],
            'msl_prod_compartigo_' => [
                'model' => ProductArticleComplement::class,
                'code_field' => 'codcompartigo',
                'name_field' => 'dsccompartigo',
            ],
        ];

        $lookupLabels = [
            'msl_prod_categoria_' => 'Categorias',
            'msl_prod_colecao_' => 'Coleções',
            'msl_prod_subcolecao_' => 'Subcoleções',
            'msl_prod_cor_' => 'Cores',
            'msl_prod_marca_' => 'Marcas',
            'msl_prod_material_' => 'Materiais',
            'msl_prod_tamanho_' => 'Tamanhos',
            'msl_prod_compartigo_' => 'Compl. Artigo',
        ];

        // Total = 8 lookup tables + 1 suppliers
        if ($log) {
            $log->update(['lookup_total' => count($lookupMappings) + 1, 'lookup_processed' => 0]);
        }

        $processed = 0;

        foreach ($lookupMappings as $table => $config) {
            if ($log) {
                $log->update(['lookup_current' => $lookupLabels[$table] ?? $table]);
            }

            try {
                // Set per-query timeout on PostgreSQL (120s per table)
                DB::connection('cigam')->statement("SET statement_timeout = '120s'");
                $rows = DB::connection('cigam')->table($table)->get();
                $count = 0;

                foreach ($rows as $row) {
                    $code = trim($row->{$config['code_field']});
                    $name = mb_strtoupper(trim($row->{$config['name_field']}));

                    if (! empty($config['sanitize_name'])) {
                        $name = $this->sanitizeCollectionName($name);
                    }

                    if (empty($code)) {
                        continue;
                    }

                    $config['model']::updateOrCreate(
                        ['cigam_code' => $code],
                        ['name' => $name, 'is_active' => true]
                    );
                    $count++;
                }

                $results[$table] = $count;
            } catch (\Exception $e) {
                Log::error("ProductSync: Error syncing {$table}: {$e->getMessage()}");
                $results[$table] = 'error: '.$e->getMessage();
            }

            $processed++;
            if ($log) {
                $log->update(['lookup_processed' => $processed]);

                // Check if cancelled
                $log->refresh();
                if ($log->status === 'cancelled') {
                    return $results;
                }
            }
        }

        // Sync product suppliers (from CIGAM, separate from service suppliers)
        if ($log) {
            $log->update(['lookup_current' => 'Fornecedores']);
        }

        try {
            DB::connection('cigam')->statement("SET statement_timeout = '120s'");
            $suppliers = DB::connection('cigam')->table('msl_dfornecedor_')->get();
            $count = 0;

            foreach ($suppliers as $row) {
                $codigoFor = trim($row->codigo_for);
                if (empty($codigoFor)) {
                    continue;
                }

                ProductSupplier::updateOrCreate(
                    ['codigo_for' => $codigoFor],
                    [
                        'cnpj' => trim($row->cnpj ?? ''),
                        'razao_social' => mb_strtoupper(trim($row->razao_social ?? '')),
                        'nome_fantasia' => mb_strtoupper(trim($row->nome_fantasia ?? '')),
                        'is_active' => true,
                    ]
                );
                $count++;
            }

            $results['product_suppliers'] = $count;
        } catch (\Exception $e) {
            Log::error("ProductSync: Error syncing product suppliers: {$e->getMessage()}");
            $results['product_suppliers'] = 'error: '.$e->getMessage();
        }

        if ($log) {
            $log->update(['lookup_processed' => $processed + 1, 'lookup_current' => null]);
        }

        return $results;
    }

    /**
     * Initialize a sync: create log and count total products (lightweight).
     * Lookups should be synced separately via syncLookups().
     */
    public function initSync(string $type, ?int $userId = null, ?string $dateStart = null, ?string $dateEnd = null): ProductSyncLog
    {
        set_time_limit(120);

        $log = ProductSyncLog::create([
            'sync_type' => $type,
            'status' => 'pending',
            'started_by_user_id' => $userId,
            'date_range_start' => $dateStart,
            'date_range_end' => $dateEnd,
        ]);

        $log->markRunning();

        // Count total products to process
        $query = DB::connection('cigam')->table('msl_produtos_')
            ->select(DB::raw('COUNT(DISTINCT referencia) as total'));

        if ($type === 'by_period' && $dateStart && $dateEnd) {
            $query->where(function ($q) use ($dateStart, $dateEnd) {
                $q->whereBetween('datacadastro', [$dateStart, $dateEnd])
                    ->orWhereBetween('dataatulizado', [$dateStart, $dateEnd]);
            });
        }

        $total = $query->value('total');
        $log->update(['total_records' => $total ?? 0]);

        return $log->fresh();
    }

    /**
     * Process a chunk of products from CIGAM using keyset pagination.
     * Uses `last_reference` cursor instead of OFFSET for O(1) performance on large tables.
     */
    public function processChunk(int $logId, ?string $lastReference = null, int $size = 500, ?string $dateStart = null, ?string $dateEnd = null): array
    {
        set_time_limit(120);

        $log = ProductSyncLog::findOrFail($logId);

        if ($log->status === 'cancelled') {
            return ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'has_more' => false, 'cancelled' => true];
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        try {
            // Keyset pagination: fetch next chunk of distinct references AFTER the cursor
            $query = DB::connection('cigam')->table('msl_produtos_')
                ->select('referencia')
                ->distinct()
                ->orderBy('referencia');

            if ($lastReference !== null) {
                $query->where('referencia', '>', $lastReference);
            }

            // Filter by period if by_period sync
            if ($dateStart && $dateEnd) {
                $query->where(function ($q) use ($dateStart, $dateEnd) {
                    $q->whereBetween('datacadastro', [$dateStart, $dateEnd])
                        ->orWhereBetween('dataatulizado', [$dateStart, $dateEnd]);
                });
            }

            $references = $query->limit($size)->pluck('referencia');

            if ($references->isEmpty()) {
                return ['processed' => 0, 'inserted' => 0, 'updated' => 0, 'has_more' => false, 'last_reference' => $lastReference];
            }

            // Fetch full data for these references
            $rows = DB::connection('cigam')->table('msl_produtos_')
                ->whereIn('referencia', $references)
                ->get();

            // Group by reference
            $grouped = $rows->groupBy('referencia');

            // Track the last reference processed for cursor
            $currentLastRef = $lastReference;

            foreach ($grouped as $reference => $variants) {
                $first = $variants->first();
                $reference = trim($reference);

                if (empty($reference)) {
                    continue;
                }

                $currentLastRef = $first->referencia; // raw value for cursor (untrimmed)

                // Check if sync_locked
                $existing = Product::where('reference', $reference)->first();
                if ($existing && $existing->sync_locked) {
                    $skipped++;

                    continue;
                }

                // Upsert product (resolve merged lookup codes to their targets)
                $productData = [
                    'description' => mb_strtoupper(trim($first->descricao ?? '')),
                    'brand_cigam_code' => $this->trimOrNull($first->codmarca ?? null),
                    'collection_cigam_code' => $this->trimOrNull($first->codcolecao ?? null),
                    'subcollection_cigam_code' => $this->trimOrNull($first->codsubcolecao ?? null),
                    'category_cigam_code' => $this->trimOrNull($first->codcategoria ?? null),
                    'color_cigam_code' => $this->trimOrNull($first->codcor ?? null),
                    'material_cigam_code' => $this->trimOrNull($first->codmaterial ?? null),
                    'article_complement_cigam_code' => $this->trimOrNull($first->codcompartigo ?? null),
                    'supplier_codigo_for' => $this->trimOrNull($first->codigo_for ?? null),
                    'synced_at' => now(),
                ];

                $product = Product::where('reference', $reference)->first();

                if ($product) {
                    $product->update($productData);
                    $updated++;
                } else {
                    $product = Product::create(array_merge($productData, [
                        'reference' => $reference,
                    ]));
                    $inserted++;
                }

                // Upsert variants
                foreach ($variants as $variant) {
                    $sizeCode = $this->trimOrNull($variant->codtamanho ?? null);
                    $barcode = $this->trimOrNull($variant->codbarra ?? null);
                    $auxReference = $this->trimOrNull($variant->refauxiliar ?? null);

                    ProductVariant::updateOrCreate(
                        ['product_id' => $product->id, 'size_cigam_code' => $sizeCode],
                        [
                            'barcode' => $barcode,
                            'aux_reference' => $auxReference,
                            'is_active' => true,
                        ]
                    );
                }
            }

            $log->incrementProcessed($inserted, $updated, $skipped);

            $hasMore = $references->count() >= $size;

            return [
                'processed' => $inserted + $updated + $skipped,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'has_more' => $hasMore,
                'last_reference' => $currentLastRef,
            ];
        } catch (\Exception $e) {
            Log::error("ProductSync: Chunk error after ref '{$lastReference}': {$e->getMessage()}");
            $errors = $log->error_details ?? [];
            $errors[] = "After ref '{$lastReference}': {$e->getMessage()}";
            $log->update([
                'error_details' => $errors,
                'error_count' => count($errors),
            ]);

            return [
                'processed' => $inserted + $updated + $skipped,
                'inserted' => $inserted,
                'updated' => $updated,
                'has_more' => true,
                'last_reference' => $lastReference,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync prices from msl_prod_valor_.
     */
    public function syncPrices(int $logId, ?string $dateStart = null, ?string $dateEnd = null): array
    {
        set_time_limit(0);

        $log = ProductSyncLog::findOrFail($logId);
        $updated = 0;
        $chunkSize = 1000;

        try {
            $query = DB::connection('cigam')->table('msl_prod_valor_')
                ->select('msl_prod_valor_.referencia', 'msl_prod_valor_.min_vlrvenda', 'msl_prod_valor_.min_vlrcusto');

            if ($dateStart && $dateEnd) {
                $query->join('msl_produtos_', 'msl_prod_valor_.referencia', '=', 'msl_produtos_.referencia')
                    ->where(function ($q) use ($dateStart, $dateEnd) {
                        $q->whereBetween('msl_produtos_.datacadastro', [$dateStart, $dateEnd])
                            ->orWhereBetween('msl_produtos_.dataatulizado', [$dateStart, $dateEnd]);
                    })
                    ->distinct();
            }

            // Count total first
            $total = (clone $query)->count();
            $log->update(['price_total' => $total, 'price_processed' => 0]);

            $processed = 0;

            // Process in chunks using orderBy + offset to avoid loading all into memory
            $query->orderBy('msl_prod_valor_.referencia');

            foreach ($query->lazy($chunkSize) as $row) {
                $reference = trim($row->referencia);
                if (! empty($reference)) {
                    $affected = Product::where('reference', $reference)
                        ->where('sync_locked', false)
                        ->update([
                            'sale_price' => $row->min_vlrvenda ?? 0,
                            'cost_price' => $row->min_vlrcusto ?? 0,
                        ]);

                    if ($affected) {
                        $updated++;
                    }
                }

                $processed++;

                // Update progress and check cancellation every chunk
                if ($processed % $chunkSize === 0) {
                    $log->update(['price_processed' => $processed]);

                    $log->refresh();
                    if ($log->status === 'cancelled') {
                        return ['updated' => $updated, 'cancelled' => true];
                    }
                }
            }

            // Final progress update
            $log->update(['price_processed' => $processed]);

            return ['updated' => $updated];
        } catch (\Exception $e) {
            Log::error("ProductSync: Price sync error: {$e->getMessage()}");

            return ['updated' => $updated, 'error' => $e->getMessage()];
        }
    }

    /**
     * Finalize a sync log.
     */
    public function finalizeSync(int $logId): ProductSyncLog
    {
        $log = ProductSyncLog::findOrFail($logId);
        $log->markCompleted();

        return $log->fresh();
    }

    /**
     * Cancel a running sync.
     */
    public function cancelSync(int $logId): ProductSyncLog
    {
        $log = ProductSyncLog::findOrFail($logId);
        $log->markCancelled();

        return $log->fresh();
    }

    /**
     * Sanitize collection names: "001 - VERAO/2025" → "VERAO"
     */
    public function sanitizeCollectionName(string $name): string
    {
        // Remove prefix before " - " or "- "
        if (preg_match('/(?:\d+\s*-\s*|\d+\s+)(.+)/', $name, $matches)) {
            $name = $matches[1];
        }

        // Remove suffix after "/"
        if (str_contains($name, '/')) {
            $name = explode('/', $name)[0];
        }

        return mb_strtoupper(trim($name));
    }

    private function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
