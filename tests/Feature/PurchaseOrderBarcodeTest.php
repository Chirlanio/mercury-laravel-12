<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderBarcode;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\EanGeneratorService;
use App\Services\PurchaseOrderBarcodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PurchaseOrderBarcodeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
        $this->supplier = $this->createTestSupplier();
    }

    public function test_ensure_for_creates_new_barcode(): void
    {
        $service = app(PurchaseOrderBarcodeService::class);
        $row = $service->ensureFor('REF-A', '36');

        $this->assertNotNull($row);
        $this->assertEquals('REF-A', $row->reference);
        $this->assertEquals('36', $row->size);
        $this->assertEquals(13, strlen($row->barcode));
        $this->assertEquals('2', $row->barcode[0]); // prefixo interno

        // Valida check digit
        $this->assertTrue(app(EanGeneratorService::class)->isValid($row->barcode));
    }

    public function test_ensure_for_is_idempotent(): void
    {
        $service = app(PurchaseOrderBarcodeService::class);
        $a = $service->ensureFor('REF-A', '36');
        $b = $service->ensureFor('REF-A', '36');

        $this->assertEquals($a->id, $b->id);
        $this->assertEquals($a->barcode, $b->barcode);
        $this->assertEquals(1, PurchaseOrderBarcode::count());
    }

    public function test_different_size_gets_different_barcode(): void
    {
        $service = app(PurchaseOrderBarcodeService::class);
        $a = $service->ensureFor('REF-A', '36');
        $b = $service->ensureFor('REF-A', '37');

        $this->assertNotEquals($a->barcode, $b->barcode);
    }

    public function test_ensure_for_order_processes_all_items(): void
    {
        $order = $this->createTestOrderWithItems();

        $service = app(PurchaseOrderBarcodeService::class);
        $result = $service->ensureForOrder($order);

        $this->assertEquals(3, $result['generated']);
        $this->assertEquals(0, $result['existing']);

        // Re-execução: tudo já existe
        $result2 = $service->ensureForOrder($order);
        $this->assertEquals(0, $result2['generated']);
        $this->assertEquals(3, $result2['existing']);
    }

    public function test_lookup_for_items_returns_map(): void
    {
        $order = $this->createTestOrderWithItems();
        $service = app(PurchaseOrderBarcodeService::class);
        $service->ensureForOrder($order);

        $map = $service->lookupForItems($order->items);

        $this->assertCount(3, $map);
        foreach ($order->items as $item) {
            $key = $item->reference . '|' . $item->size;
            $this->assertArrayHasKey($key, $map);
            $this->assertEquals(13, strlen($map[$key]));
        }
    }

    public function test_endpoint_generates_barcodes_for_order(): void
    {
        $order = $this->createTestOrderWithItems();

        $response = $this->actingAs($this->adminUser)
            ->post(route('purchase-orders.generate-barcodes', $order->id));

        $response->assertRedirect();
        $this->assertEquals(3, PurchaseOrderBarcode::count());
    }

    public function test_show_endpoint_includes_barcode_in_items(): void
    {
        $order = $this->createTestOrderWithItems();
        app(PurchaseOrderBarcodeService::class)->ensureForOrder($order);

        $response = $this->actingAs($this->adminUser)
            ->get(route('purchase-orders.show', $order->id));

        $response->assertStatus(200);
        $data = $response->json('order');
        $this->assertNotEmpty($data['items']);
        foreach ($data['items'] as $item) {
            $this->assertNotNull($item['barcode']);
            $this->assertEquals(13, strlen($item['barcode']));
        }
    }

    protected function createTestOrderWithItems(): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'order_number' => 'PO-BC-' . uniqid(),
            'season' => 'V1', 'collection' => 'C1', 'release_name' => 'L1',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->code,
            'order_date' => '2026-01-15',
            'status' => PurchaseOrderStatus::PENDING->value,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        foreach (['34', '35', '36'] as $size) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'reference' => 'REF-X',
                'size' => $size,
                'description' => 'Item',
                'unit_cost' => 100,
                'quantity_ordered' => 5,
            ]);
        }

        return $order->fresh('items');
    }
}
