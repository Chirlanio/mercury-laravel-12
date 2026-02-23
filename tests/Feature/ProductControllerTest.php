<?php

namespace Tests\Feature;

use App\Jobs\ProductSyncJob;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductCollection;
use App\Models\ProductColor;
use App\Models\ProductMaterial;
use App\Models\ProductSize;
use App\Models\ProductSyncLog;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Services\ProductSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    // ==================== INDEX ====================

    public function test_products_index_requires_authentication(): void
    {
        $response = $this->get('/products');
        $response->assertRedirect('/login');
    }

    public function test_products_index_requires_view_products_permission(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/products');
        // USER role does not have VIEW_PRODUCTS in the plan, but per the Role.php update it does not have it
        // Actually per Phase 2: USER has no product permissions
        // Wait - we DID add VIEW_PRODUCTS to USER role? No. Let me check the plan:
        // "USER: nenhuma" - but in reality I only added VIEW_PRODUCTS to SUPPORT.
        // However, the regularUser has USER role which has NO product permissions.
        $response->assertStatus(403);
    }

    public function test_products_index_accessible_by_support_user(): void
    {
        $response = $this->actingAs($this->supportUser)->get('/products');
        $response->assertStatus(200);
    }

    public function test_products_index_displays_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/products');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Products/Index'));
    }

    public function test_products_index_returns_expected_props(): void
    {
        $this->createTestProduct();

        $response = $this->actingAs($this->adminUser)->get('/products');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Products/Index')
            ->has('products')
            ->has('filters')
            ->has('stats')
            ->has('cigamAvailable')
            ->has('activeSyncLog')
        );
    }

    public function test_products_index_paginates_results(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createTestProduct();
        }

        $response = $this->actingAs($this->adminUser)->get('/products');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertCount(5, $props['products']['data']);
    }

    public function test_products_index_filters_by_search(): void
    {
        $this->createTestProduct(['reference' => 'SPECIAL-REF-123', 'description' => 'SAPATO AZUL']);
        $this->createTestProduct(['reference' => 'OTHER-REF-456', 'description' => 'BOLSA PRETA']);

        $response = $this->actingAs($this->adminUser)->get('/products?search=SAPATO');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertCount(1, $props['products']['data']);
        $this->assertEquals('SAPATO AZUL', $props['products']['data'][0]['description']);
    }

    public function test_products_index_filters_by_brand(): void
    {
        $brand = $this->createTestProductBrand(['cigam_code' => 'AREZZO', 'name' => 'AREZZO']);

        $this->createTestProduct(['brand_cigam_code' => 'AREZZO']);
        $this->createTestProduct(['brand_cigam_code' => 'OTHER']);

        $response = $this->actingAs($this->adminUser)->get('/products?brand=AREZZO');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertCount(1, $props['products']['data']);
    }

    public function test_products_index_filters_by_active_status(): void
    {
        $this->createTestProduct(['is_active' => true]);
        $this->createTestProduct(['is_active' => false]);

        $response = $this->actingAs($this->adminUser)->get('/products?is_active=1');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertCount(1, $props['products']['data']);
    }

    public function test_products_index_filters_by_sync_locked(): void
    {
        $this->createTestProduct(['sync_locked' => true]);
        $this->createTestProduct(['sync_locked' => false]);

        $response = $this->actingAs($this->adminUser)->get('/products?sync_locked=1');
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $this->assertCount(1, $props['products']['data']);
    }

    public function test_products_index_includes_stats(): void
    {
        $this->createTestProduct(['is_active' => true, 'sync_locked' => false]);
        $this->createTestProduct(['is_active' => true, 'sync_locked' => true]);
        $this->createTestProduct(['is_active' => false]);

        $response = $this->actingAs($this->adminUser)->get('/products');
        $props = $response->original->getData()['page']['props'];

        $this->assertEquals(3, $props['stats']['total']);
        $this->assertEquals(2, $props['stats']['active']);
        $this->assertEquals(1, $props['stats']['sync_locked']);
    }

    // ==================== SHOW ====================

    public function test_show_returns_product_with_variants(): void
    {
        $product = $this->createTestProduct();
        $this->createTestProductVariant($product, ['barcode' => '111']);
        $this->createTestProductVariant($product, ['barcode' => '222']);

        $response = $this->actingAs($this->adminUser)->getJson('/products/' . $product->id);
        $response->assertStatus(200);
        $response->assertJsonPath('reference', $product->reference);
        $response->assertJsonCount(2, 'variants');
    }

    // ==================== EDIT ====================

    public function test_edit_returns_product_and_options(): void
    {
        $product = $this->createTestProduct();

        $response = $this->actingAs($this->adminUser)->getJson('/products/' . $product->id . '/edit');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'product',
            'options' => ['brands', 'collections', 'categories', 'colors', 'materials'],
        ]);
    }

    public function test_edit_requires_edit_permission(): void
    {
        $product = $this->createTestProduct();

        $response = $this->actingAs($this->supportUser)->getJson('/products/' . $product->id . '/edit');
        $response->assertStatus(403);
    }

    // ==================== UPDATE ====================

    public function test_update_modifies_product(): void
    {
        $product = $this->createTestProduct(['description' => 'OLD DESC']);

        $response = $this->actingAs($this->adminUser)->putJson('/products/' . $product->id, [
            'description' => 'NEW DESC',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'description' => 'NEW DESC',
        ]);
    }

    public function test_update_sets_sync_locked(): void
    {
        $product = $this->createTestProduct(['sync_locked' => false]);

        $response = $this->actingAs($this->adminUser)->putJson('/products/' . $product->id, [
            'description' => 'UPDATED',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'sync_locked' => true,
        ]);
    }

    public function test_update_requires_description(): void
    {
        $product = $this->createTestProduct();

        $response = $this->actingAs($this->adminUser)->putJson('/products/' . $product->id, [
            'description' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('description');
    }

    public function test_update_requires_edit_permission(): void
    {
        $product = $this->createTestProduct();

        $response = $this->actingAs($this->supportUser)->putJson('/products/' . $product->id, [
            'description' => 'TEST',
        ]);

        $response->assertStatus(403);
    }

    // ==================== UNLOCK SYNC ====================

    public function test_unlock_sync_removes_lock(): void
    {
        $product = $this->createTestProduct(['sync_locked' => true]);

        $response = $this->actingAs($this->adminUser)->postJson('/products/' . $product->id . '/unlock-sync');

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'sync_locked' => false,
        ]);
    }

    // ==================== VARIANTS ====================

    public function test_store_variant_creates_new_variant(): void
    {
        $product = $this->createTestProduct();

        $response = $this->actingAs($this->adminUser)->postJson('/products/' . $product->id . '/variants', [
            'barcode' => '9999999999',
            'size_cigam_code' => 'TAM38',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'barcode' => '9999999999',
        ]);
    }

    public function test_update_variant_modifies_existing(): void
    {
        $product = $this->createTestProduct();
        $variant = $this->createTestProductVariant($product);

        $response = $this->actingAs($this->adminUser)->putJson(
            '/products/' . $product->id . '/variants/' . $variant->id,
            ['barcode' => 'NEWBARCODE']
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'barcode' => 'NEWBARCODE',
        ]);
    }

    public function test_generate_ean_creates_valid_code(): void
    {
        $product = $this->createTestProduct();
        $variant = $this->createTestProductVariant($product, ['aux_reference' => null]);

        $response = $this->actingAs($this->adminUser)->postJson(
            '/products/' . $product->id . '/variants/' . $variant->id . '/generate-ean'
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['ean', 'variant']);

        $ean = $response->json('ean');
        $this->assertMatchesRegularExpression('/^\d{13}$/', $ean);
        $this->assertEquals('2', $ean[0]); // Prefix 2 for internal codes
    }

    public function test_generate_ean_rejects_wrong_variant_product(): void
    {
        $product1 = $this->createTestProduct();
        $product2 = $this->createTestProduct();
        $variant = $this->createTestProductVariant($product2);

        $response = $this->actingAs($this->adminUser)->postJson(
            '/products/' . $product1->id . '/variants/' . $variant->id . '/generate-ean'
        );

        $response->assertStatus(404);
    }

    // ==================== SYNC ====================

    public function test_sync_init_requires_sync_permission(): void
    {
        $response = $this->actingAs($this->supportUser)->postJson('/products/sync/init', [
            'type' => 'full',
        ]);

        $response->assertStatus(403);
    }

    public function test_sync_init_requires_valid_type(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/products/sync/init', [
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
    }

    public function test_sync_init_returns_503_when_cigam_unavailable(): void
    {
        // Mock the service to simulate CIGAM being unavailable
        $this->mock(ProductSyncService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(false);
        });

        $response = $this->actingAs($this->adminUser)->postJson('/products/sync/init', [
            'type' => 'full',
        ]);

        $response->assertStatus(503);
    }

    public function test_sync_init_dispatches_job(): void
    {
        Queue::fake();

        $this->mock(ProductSyncService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('initSync')->andReturnUsing(function () {
                return ProductSyncLog::create([
                    'sync_type' => 'full',
                    'status' => 'running',
                    'total_records' => 100,
                    'started_at' => now(),
                    'started_by_user_id' => $this->adminUser->id,
                ]);
            });
        });

        $response = $this->actingAs($this->adminUser)->postJson('/products/sync/init', [
            'type' => 'full',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['log']);

        Queue::assertPushed(ProductSyncJob::class, function ($job) {
            return $job->syncType === 'full';
        });
    }

    public function test_sync_init_rejects_when_already_running(): void
    {
        $this->createTestProductSyncLog(['status' => 'running', 'completed_at' => null]);

        $this->mock(ProductSyncService::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
        });

        $response = $this->actingAs($this->adminUser)->postJson('/products/sync/init', [
            'type' => 'full',
        ]);

        $response->assertStatus(409);
        $response->assertJsonFragment(['error' => 'Já existe uma sincronização em andamento.']);
    }

    public function test_sync_status_returns_log_data(): void
    {
        $log = $this->createTestProductSyncLog([
            'status' => 'running',
            'current_phase' => 'products',
            'total_records' => 500,
            'processed_records' => 250,
            'inserted_records' => 200,
            'updated_records' => 50,
            'completed_at' => null,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/products/sync/status/' . $log->id);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'status' => 'running',
            'current_phase' => 'products',
            'total_records' => 500,
            'processed_records' => 250,
        ]);
    }

    public function test_sync_status_requires_sync_permission(): void
    {
        $log = $this->createTestProductSyncLog();

        $response = $this->actingAs($this->supportUser)->getJson('/products/sync/status/' . $log->id);
        $response->assertStatus(403);
    }

    public function test_sync_lookups_requires_sync_permission(): void
    {
        $response = $this->actingAs($this->supportUser)->postJson('/products/sync/lookups');
        $response->assertStatus(403);
    }

    public function test_sync_chunk_requires_valid_log_id(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/products/sync/chunk', [
            'log_id' => 99999,
        ]);

        $response->assertStatus(422);
    }

    public function test_sync_finalize_completes_log(): void
    {
        $log = $this->createTestProductSyncLog(['status' => 'running', 'completed_at' => null]);

        $response = $this->actingAs($this->adminUser)->postJson('/products/sync/finalize', [
            'log_id' => $log->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('product_sync_logs', [
            'id' => $log->id,
            'status' => 'completed',
        ]);
    }

    public function test_sync_cancel_marks_log_cancelled(): void
    {
        $log = $this->createTestProductSyncLog(['status' => 'running', 'completed_at' => null]);

        $response = $this->actingAs($this->adminUser)->postJson('/products/sync/cancel', [
            'log_id' => $log->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('product_sync_logs', [
            'id' => $log->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_sync_logs_returns_paginated_history(): void
    {
        $this->createTestProductSyncLog();
        $this->createTestProductSyncLog(['sync_type' => 'prices_only']);

        $response = $this->actingAs($this->adminUser)->getJson('/products/sync/logs');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    // ==================== FILTER OPTIONS ====================

    public function test_filter_options_returns_active_lookups(): void
    {
        ProductBrand::create(['cigam_code' => 'B1', 'name' => 'BRAND 1', 'is_active' => true]);
        ProductBrand::create(['cigam_code' => 'B2', 'name' => 'BRAND 2', 'is_active' => false]);

        $response = $this->actingAs($this->adminUser)->getJson('/products/filter-options');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'brands');
        $response->assertJsonStructure([
            'brands', 'collections', 'categories', 'colors', 'materials', 'suppliers',
        ]);
    }

    // ==================== PERMISSION MATRIX ====================

    public function test_regular_user_cannot_access_products(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/products');
        $response->assertStatus(403);
    }

    public function test_support_can_view_but_not_edit(): void
    {
        $product = $this->createTestProduct();

        // Can view
        $this->actingAs($this->supportUser)->get('/products')->assertStatus(200);
        $this->actingAs($this->supportUser)->getJson('/products/' . $product->id)->assertStatus(200);

        // Cannot edit
        $this->actingAs($this->supportUser)->getJson('/products/' . $product->id . '/edit')->assertStatus(403);
        $this->actingAs($this->supportUser)->putJson('/products/' . $product->id, ['description' => 'X'])->assertStatus(403);
    }

    public function test_admin_has_full_access(): void
    {
        $product = $this->createTestProduct();

        $this->actingAs($this->adminUser)->get('/products')->assertStatus(200);
        $this->actingAs($this->adminUser)->getJson('/products/' . $product->id)->assertStatus(200);
        $this->actingAs($this->adminUser)->getJson('/products/' . $product->id . '/edit')->assertStatus(200);
        $this->actingAs($this->adminUser)->putJson('/products/' . $product->id, ['description' => 'NEW'])->assertStatus(200);
    }
}
