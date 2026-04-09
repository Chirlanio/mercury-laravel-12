<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StockAudit;
use App\Models\StockAuditItem;
use App\Services\StockAuditCountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class StockAuditCountingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected StockAudit $audit;

    protected Product $product;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);

        $this->product = Product::create([
            'reference' => 'REF001',
            'description' => 'Produto Teste',
            'sale_price' => 99.90,
            'cost_price' => 49.90,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'barcode' => '1234567890',
            'aux_reference' => '7891234567890',
            'size_cigam_code' => '38',
            'is_active' => true,
        ]);

        $this->audit = StockAudit::create([
            'store_id' => $this->store->id,
            'audit_type' => 'total',
            'status' => 'counting',
            'requires_second_count' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    public function test_register_barcode_scan_creates_item(): void
    {
        $service = app(StockAuditCountingService::class);

        $result = $service->registerScan(
            $this->audit,
            '7891234567890',
            1,
            1,
            null,
            $this->adminUser->id
        );

        $this->assertFalse($result['error']);
        $this->assertEquals(1, $result['new_count']);
        $this->assertDatabaseHas('stock_audit_items', [
            'audit_id' => $this->audit->id,
            'product_barcode' => '7891234567890',
            'count_1' => 1,
        ]);
    }

    public function test_scan_increments_existing_item(): void
    {
        $service = app(StockAuditCountingService::class);

        $service->registerScan($this->audit, '7891234567890', 1, 1, null, $this->adminUser->id);
        $result = $service->registerScan($this->audit, '7891234567890', 1, 1, null, $this->adminUser->id);

        $this->assertFalse($result['error']);
        $this->assertEquals(2, $result['new_count']);
    }

    public function test_unknown_barcode_returns_error(): void
    {
        $service = app(StockAuditCountingService::class);

        $result = $service->registerScan(
            $this->audit,
            '0000000000000',
            1,
            1,
            null,
            $this->adminUser->id
        );

        $this->assertTrue($result['error']);
    }

    public function test_cannot_count_on_non_counting_audit(): void
    {
        $this->audit->update(['status' => 'draft']);
        $service = app(StockAuditCountingService::class);

        $result = $service->registerScan(
            $this->audit,
            '7891234567890',
            1,
            1,
            null,
            $this->adminUser->id
        );

        $this->assertTrue($result['error']);
    }

    public function test_finalize_round(): void
    {
        $service = app(StockAuditCountingService::class);

        // Add an item first
        $service->registerScan($this->audit, '7891234567890', 1, 5, null, $this->adminUser->id);

        $result = $service->finalizeRound($this->audit, 1, $this->adminUser->id);

        $this->assertFalse($result['error']);
        $this->assertTrue($this->audit->fresh()->count_1_finalized);
    }

    public function test_cannot_finalize_empty_round(): void
    {
        $service = app(StockAuditCountingService::class);

        $result = $service->finalizeRound($this->audit, 1, $this->adminUser->id);

        $this->assertTrue($result['error']);
    }

    public function test_cannot_count_on_finalized_round(): void
    {
        $this->audit->update(['count_1_finalized' => true]);
        $service = app(StockAuditCountingService::class);

        $result = $service->registerScan(
            $this->audit,
            '7891234567890',
            1,
            1,
            null,
            $this->adminUser->id
        );

        $this->assertTrue($result['error']);
    }

    public function test_counting_summary(): void
    {
        $service = app(StockAuditCountingService::class);

        // Create some items
        StockAuditItem::create([
            'audit_id' => $this->audit->id,
            'product_reference' => 'REF001',
            'product_description' => 'Produto 1',
            'product_barcode' => '111',
            'count_1' => 5,
        ]);

        StockAuditItem::create([
            'audit_id' => $this->audit->id,
            'product_reference' => 'REF002',
            'product_description' => 'Produto 2',
            'product_barcode' => '222',
        ]);

        $summary = $service->getCountingSummary($this->audit);

        $this->assertEquals(2, $summary['total_items']);
        $this->assertEquals(1, $summary['round_1']['counted']);
    }

    public function test_lookup_product_by_aux_reference(): void
    {
        $service = app(StockAuditCountingService::class);

        $result = $service->lookupProduct('7891234567890');

        $this->assertNotNull($result);
        $this->assertEquals('REF001', $result['reference']);
        $this->assertEquals($this->variant->id, $result['variant_id']);
    }

    public function test_scan_via_api_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('stock-audits.count', $this->audit), [
                'barcode' => '7891234567890',
                'round' => 1,
                'quantity' => 3,
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['error' => false]);
    }
}
