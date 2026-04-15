<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\Movement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\PurchaseOrderCigamMatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PurchaseOrderReceiptTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Store $store2;

    protected Supplier $supplier;

    protected PurchaseOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
        $this->store2 = Store::factory()->create(['code' => 'Z425', 'name' => 'Loja Secundária']);
        $this->supplier = $this->createTestSupplier(['nome_fantasia' => 'Fornecedor Teste']);

        $this->order = PurchaseOrder::create([
            'order_number' => 'PO-100',
            'season' => 'INVERNO 2026',
            'collection' => 'INVERNO 1',
            'release_name' => 'L1',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->code,
            'order_date' => '2026-01-15',
            'status' => PurchaseOrderStatus::INVOICED->value, // já faturado, pronto para receber
            'created_by_user_id' => $this->adminUser->id,
        ]);

        // 2 items: 10 + 5 = 15 unidades
        PurchaseOrderItem::create([
            'purchase_order_id' => $this->order->id,
            'reference' => 'REF-001',
            'size' => '36',
            'description' => 'Tênis Preto',
            'unit_cost' => 100.00,
            'selling_price' => 250.00,
            'quantity_ordered' => 10,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $this->order->id,
            'reference' => 'REF-002',
            'size' => 'M',
            'description' => 'Camiseta Branca',
            'unit_cost' => 30.00,
            'selling_price' => 80.00,
            'quantity_ordered' => 5,
        ]);
    }

    // ------------------------------------------------------------------
    // Receipt manual
    // ------------------------------------------------------------------

    public function test_can_register_partial_receipt_manually(): void
    {
        $items = $this->order->items()->get();

        $response = $this->actingAs($this->adminUser)->post(
            route('purchase-orders.receipts.store', $this->order->id),
            [
                'invoice_number' => 'NF-12345',
                'items' => [
                    ['purchase_order_item_id' => $items[0]->id, 'quantity' => 4],
                    ['purchase_order_item_id' => $items[1]->id, 'quantity' => 2],
                ],
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_order_receipts', [
            'purchase_order_id' => $this->order->id,
            'invoice_number' => 'NF-12345',
            'source' => 'manual',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        // quantity_received agregado
        $this->assertEquals(4, $items[0]->fresh()->quantity_received);
        $this->assertEquals(2, $items[1]->fresh()->quantity_received);
    }

    public function test_full_receipt_auto_transitions_to_delivered(): void
    {
        $items = $this->order->items()->get();

        $this->actingAs($this->adminUser)->post(
            route('purchase-orders.receipts.store', $this->order->id),
            [
                'items' => [
                    ['purchase_order_item_id' => $items[0]->id, 'quantity' => 10],
                    ['purchase_order_item_id' => $items[1]->id, 'quantity' => 5],
                ],
            ]
        );

        $this->assertEquals('delivered', $this->order->fresh()->status->value);
        $this->assertNotNull($this->order->fresh()->delivered_at);
    }

    public function test_partial_receipt_in_pending_order_transitions_to_partial_invoiced(): void
    {
        // Voltar a ordem para PENDING
        $this->order->update(['status' => PurchaseOrderStatus::PENDING->value]);
        $items = $this->order->items()->get();

        $this->actingAs($this->adminUser)->post(
            route('purchase-orders.receipts.store', $this->order->id),
            [
                'items' => [
                    ['purchase_order_item_id' => $items[0]->id, 'quantity' => 3],
                ],
            ]
        );

        $this->assertEquals('partial_invoiced', $this->order->fresh()->status->value);
    }

    public function test_cannot_receive_more_than_remaining(): void
    {
        $item = $this->order->items()->first();

        $response = $this->actingAs($this->adminUser)->post(
            route('purchase-orders.receipts.store', $this->order->id),
            [
                'items' => [
                    ['purchase_order_item_id' => $item->id, 'quantity' => 999],
                ],
            ]
        );

        $response->assertSessionHasErrors('items');
        $this->assertEquals(0, $item->fresh()->quantity_received);
    }

    public function test_cannot_receive_in_cancelled_order(): void
    {
        $this->order->update(['status' => PurchaseOrderStatus::CANCELLED->value]);
        $item = $this->order->items()->first();

        $response = $this->actingAs($this->adminUser)->post(
            route('purchase-orders.receipts.store', $this->order->id),
            [
                'items' => [
                    ['purchase_order_item_id' => $item->id, 'quantity' => 1],
                ],
            ]
        );

        $response->assertSessionHasErrors('order');
    }

    public function test_user_without_receive_permission_cannot_register(): void
    {
        $item = $this->order->items()->first();

        $response = $this->actingAs($this->regularUser)->post(
            route('purchase-orders.receipts.store', $this->order->id),
            [
                'items' => [
                    ['purchase_order_item_id' => $item->id, 'quantity' => 1],
                ],
            ]
        );

        // regularUser não tem nem VIEW (já bloqueia antes)
        $response->assertStatus(403);
    }

    public function test_validates_required_items(): void
    {
        $response = $this->actingAs($this->adminUser)->post(
            route('purchase-orders.receipts.store', $this->order->id),
            ['items' => []]
        );

        $response->assertSessionHasErrors('items');
    }

    public function test_multiple_receipts_accumulate(): void
    {
        $item = $this->order->items()->first();

        // Receipt 1: 4 unidades
        $this->actingAs($this->adminUser)->post(
            route('purchase-orders.receipts.store', $this->order->id),
            [
                'invoice_number' => 'NF-A',
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 4]],
            ]
        );

        // Receipt 2: 6 unidades
        $this->actingAs($this->adminUser)->post(
            route('purchase-orders.receipts.store', $this->order->id),
            [
                'invoice_number' => 'NF-B',
                'items' => [['purchase_order_item_id' => $item->id, 'quantity' => 6]],
            ]
        );

        $this->assertEquals(10, $item->fresh()->quantity_received);
        $this->assertEquals(2, PurchaseOrderReceipt::where('purchase_order_id', $this->order->id)->count());
    }

    // ------------------------------------------------------------------
    // CIGAM matcher
    // ------------------------------------------------------------------

    public function test_cigam_matcher_detects_movement_by_ref_size(): void
    {
        // Cria um Movement do CIGAM que casa com REF-001/36, na loja certa
        $movement = Movement::create([
            'movement_date' => '2026-02-01',
            'store_code' => $this->store->code,
            'movement_code' => PurchaseOrderCigamMatcherService::CIGAM_PURCHASE_ENTRY_CODE,
            'entry_exit' => 'E',
            'invoice_number' => 'NF-CIGAM-1',
            'ref_size' => 'REF-00136', // ref + size sem separador
            'barcode' => '1234567890123',
            'quantity' => 7,
            'cost_price' => 95.50,
            'realized_value' => 668.50,
            'sale_price' => 250.00,
            'discount_value' => 0,
            'net_value' => 668.50,
            'net_quantity' => 7,
        ]);

        $matcher = app(PurchaseOrderCigamMatcherService::class);
        $result = $matcher->matchOrder($this->order);

        $this->assertEquals(1, $result['receipts_created']);
        $this->assertEquals(1, $result['items_matched']);

        // Receipt criado com source cigam_match
        $receipt = PurchaseOrderReceipt::where('purchase_order_id', $this->order->id)->first();
        $this->assertEquals('cigam_match', $receipt->source);
        $this->assertEquals('NF-CIGAM-1', $receipt->invoice_number);
        $this->assertNull($receipt->created_by_user_id);

        // Item recebido
        $item = $this->order->items()->where('reference', 'REF-001')->first();
        $this->assertEquals(7, $item->fresh()->quantity_received);

        // Receipt item linkado ao movement
        $this->assertDatabaseHas('purchase_order_receipt_items', [
            'matched_movement_id' => $movement->id,
            'unit_cost_cigam' => 95.50,
        ]);
    }

    public function test_cigam_matcher_ignores_other_store(): void
    {
        Movement::create([
            'movement_date' => '2026-02-01',
            'store_code' => $this->store2->code, // OUTRA loja
            'movement_code' => PurchaseOrderCigamMatcherService::CIGAM_PURCHASE_ENTRY_CODE,
            'entry_exit' => 'E',
            'invoice_number' => 'NF-OTHER',
            'ref_size' => 'REF-00136',
            'quantity' => 5,
            'cost_price' => 100.00,
            'realized_value' => 500.00,
            'sale_price' => 250.00,
            'discount_value' => 0,
            'net_value' => 500.00,
            'net_quantity' => 5,
        ]);

        $matcher = app(PurchaseOrderCigamMatcherService::class);
        $result = $matcher->matchOrder($this->order);

        $this->assertEquals(0, $result['receipts_created']);
        $this->assertEquals(0, $this->order->items()->where('reference', 'REF-001')->first()->quantity_received);
    }

    public function test_cigam_matcher_ignores_movement_already_matched(): void
    {
        $movement = Movement::create([
            'movement_date' => '2026-02-01',
            'store_code' => $this->store->code,
            'movement_code' => PurchaseOrderCigamMatcherService::CIGAM_PURCHASE_ENTRY_CODE,
            'entry_exit' => 'E',
            'invoice_number' => 'NF-IDEM',
            'ref_size' => 'REF-00136',
            'quantity' => 3,
            'cost_price' => 100.00,
            'realized_value' => 300.00,
            'sale_price' => 250.00,
            'discount_value' => 0,
            'net_value' => 300.00,
            'net_quantity' => 3,
        ]);

        $matcher = app(PurchaseOrderCigamMatcherService::class);

        // 1ª passada
        $result1 = $matcher->matchOrder($this->order);
        $this->assertEquals(1, $result1['receipts_created']);

        // 2ª passada (re-execução): mesmo movement não deve gerar receipt novo
        $result2 = $matcher->matchOrder($this->order);
        $this->assertEquals(0, $result2['receipts_created']);

        // Total continua só 1 receipt
        $this->assertEquals(1, PurchaseOrderReceipt::where('purchase_order_id', $this->order->id)->count());
    }

    public function test_cigam_matcher_groups_movements_by_invoice(): void
    {
        // 2 movements, mesma NF, diferentes refs
        Movement::create([
            'movement_date' => '2026-02-01',
            'store_code' => $this->store->code,
            'movement_code' => PurchaseOrderCigamMatcherService::CIGAM_PURCHASE_ENTRY_CODE,
            'entry_exit' => 'E',
            'invoice_number' => 'NF-MULTI',
            'ref_size' => 'REF-00136',
            'quantity' => 4,
            'cost_price' => 100, 'realized_value' => 400,
            'sale_price' => 250, 'discount_value' => 0,
            'net_value' => 400, 'net_quantity' => 4,
        ]);
        Movement::create([
            'movement_date' => '2026-02-01',
            'store_code' => $this->store->code,
            'movement_code' => PurchaseOrderCigamMatcherService::CIGAM_PURCHASE_ENTRY_CODE,
            'entry_exit' => 'E',
            'invoice_number' => 'NF-MULTI',
            'ref_size' => 'REF-002M',
            'quantity' => 3,
            'cost_price' => 30, 'realized_value' => 90,
            'sale_price' => 80, 'discount_value' => 0,
            'net_value' => 90, 'net_quantity' => 3,
        ]);

        $matcher = app(PurchaseOrderCigamMatcherService::class);
        $result = $matcher->matchOrder($this->order);

        $this->assertEquals(1, $result['receipts_created']); // agrupados em 1 receipt
        $this->assertEquals(2, $result['items_matched']);

        $receipt = PurchaseOrderReceipt::where('invoice_number', 'NF-MULTI')->first();
        $this->assertEquals(2, $receipt->items()->count());
    }

    public function test_cigam_matcher_caps_at_remaining_quantity(): void
    {
        // Item REF-001/36 já recebeu 8 manualmente
        $item = $this->order->items()->where('reference', 'REF-001')->first();
        $item->update(['quantity_received' => 8]);

        // CIGAM tem movement de 5, mas só restam 2 (10 - 8)
        Movement::create([
            'movement_date' => '2026-02-01',
            'store_code' => $this->store->code,
            'movement_code' => PurchaseOrderCigamMatcherService::CIGAM_PURCHASE_ENTRY_CODE,
            'entry_exit' => 'E',
            'invoice_number' => 'NF-OVER',
            'ref_size' => 'REF-00136',
            'quantity' => 5,
            'cost_price' => 100, 'realized_value' => 500,
            'sale_price' => 250, 'discount_value' => 0,
            'net_value' => 500, 'net_quantity' => 5,
        ]);

        $matcher = app(PurchaseOrderCigamMatcherService::class);
        $matcher->matchOrder($this->order);

        // Capou em 2 (saldo restante)
        $this->assertEquals(10, $item->fresh()->quantity_received);
    }
}
