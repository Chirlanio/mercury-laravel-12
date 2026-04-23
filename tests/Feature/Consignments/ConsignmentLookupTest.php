<?php

namespace Tests\Feature\Consignments;

use App\Models\Movement;
use App\Models\MovementType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ConsignmentLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobertura de ConsignmentLookupService — autocomplete de produtos (M8)
 * + lookup de NF de saída/retorno no CIGAM (codes 20/21).
 */
class ConsignmentLookupTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected ConsignmentLookupService $service;

    protected Product $product;

    protected ProductVariant $variant36;

    protected ProductVariant $variant37;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        MovementType::firstOrCreate(['code' => 20], ['description' => 'Remessa']);
        MovementType::firstOrCreate(['code' => 21], ['description' => 'Retorno']);

        $this->product = Product::create([
            'reference' => 'SAND-001',
            'description' => 'Sandália Azul',
            'sale_price' => 199.90,
            'is_active' => true,
        ]);

        $this->variant36 = ProductVariant::create([
            'product_id' => $this->product->id,
            'barcode' => '1234567890123',
            'size_cigam_code' => 'U36',
            'is_active' => true,
        ]);

        $this->variant37 = ProductVariant::create([
            'product_id' => $this->product->id,
            'barcode' => '1234567890130',
            'size_cigam_code' => 'U37',
            'is_active' => true,
        ]);

        $this->service = app(ConsignmentLookupService::class);
    }

    // ------------------------------------------------------------------
    // searchProducts — autocomplete
    // ------------------------------------------------------------------

    public function test_search_by_reference_prefix(): void
    {
        $results = $this->service->searchProducts('SAND');

        $this->assertCount(1, $results);
        $this->assertEquals($this->product->id, $results[0]['product_id']);
        $this->assertCount(2, $results[0]['variants']);
    }

    public function test_search_by_description_contains(): void
    {
        $results = $this->service->searchProducts('azul');

        $this->assertCount(1, $results);
        $this->assertEquals('SAND-001', $results[0]['reference']);
    }

    public function test_search_by_ean13_exact(): void
    {
        $results = $this->service->searchProducts('1234567890123');

        $this->assertCount(1, $results);
        $this->assertEquals($this->product->id, $results[0]['product_id']);
    }

    public function test_search_excludes_inactive_products(): void
    {
        $this->product->update(['is_active' => false]);

        $results = $this->service->searchProducts('SAND');

        $this->assertCount(0, $results);
    }

    public function test_search_empty_query_returns_empty(): void
    {
        $results = $this->service->searchProducts('   ');

        $this->assertCount(0, $results);
    }

    // ------------------------------------------------------------------
    // resolveProductVariant
    // ------------------------------------------------------------------

    public function test_resolve_by_barcode_returns_product_and_variant(): void
    {
        $resolved = $this->service->resolveProductVariant(
            barcode: '1234567890123',
        );

        $this->assertNotNull($resolved);
        $this->assertEquals($this->product->id, $resolved['product']->id);
        $this->assertEquals($this->variant36->id, $resolved['variant']->id);
    }

    public function test_resolve_by_reference_and_size(): void
    {
        $resolved = $this->service->resolveProductVariant(
            reference: 'SAND-001',
            sizeCigamCode: 'U37',
        );

        $this->assertNotNull($resolved);
        $this->assertEquals($this->variant37->id, $resolved['variant']->id);
    }

    public function test_resolve_returns_null_for_unknown(): void
    {
        $resolved = $this->service->resolveProductVariant(
            reference: 'NAO-EXISTE',
        );

        $this->assertNull($resolved);
    }

    // ------------------------------------------------------------------
    // findOutboundInvoice (code=20) + findReturnInvoice (code=21)
    // ------------------------------------------------------------------

    public function test_find_outbound_invoice_returns_not_found_when_empty(): void
    {
        $result = $this->service->findOutboundInvoice('Z421', '55001');

        $this->assertFalse($result['found']);
        $this->assertEquals(0, $result['items_count']);
    }

    public function test_find_outbound_invoice_populates_items_with_resolved_products(): void
    {
        // Cria movement code=20 (saída) com ref_size casando o produto
        Movement::create([
            'store_code' => 'Z421',
            'invoice_number' => '55001',
            'movement_code' => 20,
            'movement_date' => '2026-04-23',
            'movement_time' => '10:00:00',
            'ref_size' => 'SAND-001|36',
            'barcode' => '1234567890123',
            'quantity' => 2,
            'net_quantity' => 2,
            'sale_price' => 199.90,
            'realized_value' => 399.80,
            'net_value' => 399.80,
            'discount_value' => 0,
            'entry_exit' => 'S',
            'synced_at' => now(),
        ]);

        $result = $this->service->findOutboundInvoice('Z421', '55001', '2026-04-23');

        $this->assertTrue($result['found']);
        $this->assertEquals(1, $result['items_count']);
        $this->assertCount(1, $result['items']);
        $this->assertCount(0, $result['orphan_items']);

        $item = $result['items'][0];
        $this->assertEquals($this->product->id, $item['product_id']);
        $this->assertEquals(2, $item['quantity']);
    }

    public function test_find_outbound_invoice_flags_orphan_when_product_not_in_catalog(): void
    {
        // Movement de produto que não existe no catálogo
        Movement::create([
            'store_code' => 'Z421',
            'invoice_number' => '55002',
            'movement_code' => 20,
            'movement_date' => '2026-04-23',
            'movement_time' => '10:00:00',
            'ref_size' => 'FANTASMA|99',
            'barcode' => null,
            'quantity' => 1,
            'net_quantity' => 1,
            'sale_price' => 100,
            'realized_value' => 100,
            'net_value' => 100,
            'discount_value' => 0,
            'entry_exit' => 'S',
            'synced_at' => now(),
        ]);

        $result = $this->service->findOutboundInvoice('Z421', '55002', '2026-04-23');

        $this->assertTrue($result['found']);
        $this->assertCount(0, $result['items']);
        $this->assertCount(1, $result['orphan_items']);
        $this->assertEquals('FANTASMA', $result['orphan_items'][0]['reference']);
    }

    public function test_find_return_invoice_uses_movement_code_21(): void
    {
        Movement::create([
            'store_code' => 'Z421',
            'invoice_number' => '88001',
            'movement_code' => 21,
            'movement_date' => '2026-04-23',
            'movement_time' => '10:00:00',
            'ref_size' => 'SAND-001|36',
            'barcode' => '1234567890123',
            'quantity' => 1,
            'net_quantity' => 1,
            'sale_price' => 199.90,
            'realized_value' => 199.90,
            'net_value' => 199.90,
            'discount_value' => 0,
            'entry_exit' => 'E',
            'synced_at' => now(),
        ]);

        $result = $this->service->findReturnInvoice('Z421', '88001', '2026-04-23');

        $this->assertTrue($result['found']);
        $this->assertEquals(1, $result['items_count']);
        $this->assertEquals($this->product->id, $result['items'][0]['product_id']);
    }

    public function test_find_outbound_does_not_return_code_21_movements(): void
    {
        // Mesmo invoice_number mas code=21 — não deve aparecer no outbound
        Movement::create([
            'store_code' => 'Z421',
            'invoice_number' => '55003',
            'movement_code' => 21,
            'movement_date' => '2026-04-23',
            'movement_time' => '10:00:00',
            'ref_size' => 'SAND-001|36',
            'barcode' => '1234567890123',
            'quantity' => 1,
            'net_quantity' => 1,
            'sale_price' => 199.90,
            'realized_value' => 199.90,
            'net_value' => 199.90,
            'discount_value' => 0,
            'entry_exit' => 'E',
            'synced_at' => now(),
        ]);

        $result = $this->service->findOutboundInvoice('Z421', '55003');

        $this->assertFalse($result['found']);
    }
}
