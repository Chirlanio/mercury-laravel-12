<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PurchaseOrderControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Store $store2;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
        $this->store2 = Store::factory()->create(['code' => 'Z425', 'name' => 'Loja Secundária']);

        $this->supplier = $this->createTestSupplier([
            'nome_fantasia' => 'Fornecedor Teste',
            'payment_terms_default' => '30/60/90',
        ]);
    }

    // ------------------------------------------------------------------
    // Index (listing)
    // ------------------------------------------------------------------

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('purchase-orders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PurchaseOrders/Index'));
    }

    public function test_regular_user_cannot_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('purchase-orders.index'));

        $response->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    public function test_admin_can_create_order(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.store'), [
            'order_number' => 'PO-001',
            'short_description' => 'Inverno 2026',
            'season' => 'INVERNO 2026',
            'collection' => 'INVERNO 1',
            'release_name' => 'Lançamento 1',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->code,
            'order_date' => '2026-01-15',
            'predict_date' => '2026-02-15',
            'payment_terms_raw' => '30/60/90',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_orders', [
            'order_number' => 'PO-001',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->code,
            'status' => 'pending',
        ]);

        // Histórico inicial registrado
        $order = PurchaseOrder::first();
        $this->assertEquals(1, $order->statusHistory()->count());
        $this->assertNull($order->statusHistory->first()->from_status);
        $this->assertEquals('pending', $order->statusHistory->first()->to_status);
    }

    public function test_create_rejects_duplicate_order_number(): void
    {
        $this->createTestOrder(['order_number' => 'PO-DUP']);

        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.store'), [
            'order_number' => 'PO-DUP',
            'season' => 'V1',
            'collection' => 'C1',
            'release_name' => 'L1',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->code,
            'order_date' => '2026-01-15',
        ]);

        $response->assertSessionHasErrors('order_number');
        $this->assertEquals(1, PurchaseOrder::where('order_number', 'PO-DUP')->count());
    }

    public function test_create_rejects_predict_date_before_order_date(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.store'), [
            'order_number' => 'PO-002',
            'season' => 'V1',
            'collection' => 'C1',
            'release_name' => 'L1',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->code,
            'order_date' => '2026-02-15',
            'predict_date' => '2026-01-15',
        ]);

        $response->assertSessionHasErrors('predict_date');
    }

    public function test_create_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.store'), []);

        $response->assertSessionHasErrors(['order_number', 'season', 'collection', 'release_name', 'supplier_id', 'store_id', 'order_date']);
    }

    // ------------------------------------------------------------------
    // Update
    // ------------------------------------------------------------------

    public function test_can_update_pending_order(): void
    {
        $order = $this->createTestOrder();

        $response = $this->actingAs($this->adminUser)->put(route('purchase-orders.update', $order->id), [
            'order_number' => $order->order_number,
            'season' => 'INVERNO 2026',
            'collection' => 'INVERNO 2',
            'release_name' => 'L1',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-01-16',
            'short_description' => 'Atualizada',
        ]);

        $response->assertRedirect();
        $this->assertEquals('Atualizada', $order->fresh()->short_description);
    }

    public function test_cannot_update_non_pending_order(): void
    {
        $order = $this->createTestOrder();
        $order->update(['status' => PurchaseOrderStatus::INVOICED->value]);

        $response = $this->actingAs($this->adminUser)->put(route('purchase-orders.update', $order->id), [
            'order_number' => $order->order_number,
            'season' => 'V1',
            'collection' => 'C1',
            'release_name' => 'L1',
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-01-15',
            'short_description' => 'Não deve aplicar',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertNotEquals('Não deve aplicar', $order->fresh()->short_description);
    }

    // ------------------------------------------------------------------
    // Transition (state machine)
    // ------------------------------------------------------------------

    public function test_can_transition_pending_to_invoiced(): void
    {
        $order = $this->createTestOrder();

        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.transition', $order->id), [
            'to_status' => PurchaseOrderStatus::INVOICED->value,
        ]);

        $response->assertRedirect();
        $this->assertEquals('invoiced', $order->fresh()->status->value);

        // A transição cria uma linha no histórico
        $history = $order->statusHistory()->latest('id')->first();
        $this->assertEquals('pending', $history->from_status);
        $this->assertEquals('invoiced', $history->to_status);
        $this->assertEquals($this->adminUser->id, $history->changed_by_user_id);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $order = $this->createTestOrder();

        // pending → delivered não é válido (tem que passar por invoiced primeiro)
        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.transition', $order->id), [
            'to_status' => PurchaseOrderStatus::DELIVERED->value,
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertEquals('pending', $order->fresh()->status->value);
    }

    public function test_cancel_requires_note(): void
    {
        $order = $this->createTestOrder();

        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.transition', $order->id), [
            'to_status' => PurchaseOrderStatus::CANCELLED->value,
        ]);

        $response->assertSessionHasErrors('note');
        $this->assertEquals('pending', $order->fresh()->status->value);
    }

    public function test_cancel_with_note_succeeds(): void
    {
        $order = $this->createTestOrder();

        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.transition', $order->id), [
            'to_status' => PurchaseOrderStatus::CANCELLED->value,
            'note' => 'Fornecedor cancelou o pedido',
        ]);

        $response->assertRedirect();
        $this->assertEquals('cancelled', $order->fresh()->status->value);
    }

    public function test_can_reopen_cancelled_order(): void
    {
        $order = $this->createTestOrder();
        $order->update(['status' => PurchaseOrderStatus::CANCELLED->value]);

        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.transition', $order->id), [
            'to_status' => PurchaseOrderStatus::PENDING->value,
        ]);

        $response->assertRedirect();
        $this->assertEquals('pending', $order->fresh()->status->value);
    }

    public function test_delivered_is_terminal(): void
    {
        $order = $this->createTestOrder();
        $order->update(['status' => PurchaseOrderStatus::DELIVERED->value]);

        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.transition', $order->id), [
            'to_status' => PurchaseOrderStatus::CANCELLED->value,
            'note' => 'tentativa',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertEquals('delivered', $order->fresh()->status->value);
    }

    // ------------------------------------------------------------------
    // Delete (soft)
    // ------------------------------------------------------------------

    public function test_destroy_requires_reason(): void
    {
        $order = $this->createTestOrder();

        $response = $this->actingAs($this->adminUser)->delete(route('purchase-orders.destroy', $order->id));

        $response->assertSessionHasErrors('deleted_reason');
        $this->assertNull($order->fresh()->deleted_at);
    }

    public function test_destroy_with_reason_soft_deletes(): void
    {
        $order = $this->createTestOrder();

        $response = $this->actingAs($this->adminUser)->delete(route('purchase-orders.destroy', $order->id), [
            'deleted_reason' => 'Cadastro duplicado',
        ]);

        $response->assertRedirect();
        $this->assertNotNull($order->fresh()->deleted_at);
        $this->assertEquals('Cadastro duplicado', $order->fresh()->deleted_reason);
        $this->assertEquals($this->adminUser->id, $order->fresh()->deleted_by_user_id);
    }

    // ------------------------------------------------------------------
    // Items (size matrix)
    // ------------------------------------------------------------------

    public function test_can_add_items_with_size_matrix(): void
    {
        $order = $this->createTestOrder();

        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.items.store', $order->id), [
            'items' => [[
                'reference' => 'REF-001',
                'description' => 'Tênis',
                'material' => 'Couro',
                'color' => 'Preto',
                'unit_cost' => 150.00,
                'selling_price' => 399.00,
                'sizes' => [
                    '34' => 1,
                    '35' => 2,
                    '36' => 3,
                    '37' => 3,
                    '38' => 2,
                    '39' => 1,
                ],
            ]],
        ]);

        $response->assertRedirect();
        // 6 tamanhos → 6 items criados
        $this->assertEquals(6, $order->items()->count());
        $this->assertEquals(12, $order->items()->sum('quantity_ordered'));
    }

    public function test_cannot_add_items_to_non_pending_order(): void
    {
        $order = $this->createTestOrder();
        $order->update(['status' => PurchaseOrderStatus::INVOICED->value]);

        $response = $this->actingAs($this->adminUser)->post(route('purchase-orders.items.store', $order->id), [
            'items' => [[
                'reference' => 'REF-002',
                'description' => 'Bolsa',
                'unit_cost' => 100.00,
                'sizes' => ['U' => 5],
            ]],
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertEquals(0, $order->items()->count());
    }

    // ------------------------------------------------------------------
    // Store scoping (sem MANAGE_PURCHASE_ORDERS)
    // ------------------------------------------------------------------

    public function test_support_user_sees_only_own_store(): void
    {
        // support não tem MANAGE_PURCHASE_ORDERS → store scoping ativo
        $this->supportUser->update(['store_id' => $this->store->code]);

        // Ordem na loja do support
        $ownOrder = $this->createTestOrder(['store_id' => $this->store->code, 'order_number' => 'PO-OWN']);
        // Ordem em outra loja
        $otherOrder = $this->createTestOrder(['store_id' => $this->store2->code, 'order_number' => 'PO-OTHER']);

        $response = $this->actingAs($this->supportUser)->get(route('purchase-orders.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'PO-OWN')
        );
    }

    public function test_support_user_cannot_access_other_store_order(): void
    {
        $this->supportUser->update(['store_id' => $this->store->code]);

        $otherOrder = $this->createTestOrder(['store_id' => $this->store2->code]);

        $response = $this->actingAs($this->supportUser)->get(route('purchase-orders.show', $otherOrder->id));
        $response->assertStatus(403);
    }

    public function test_support_user_cannot_create_for_other_store(): void
    {
        $this->supportUser->update(['store_id' => $this->store->code]);

        $response = $this->actingAs($this->supportUser)->post(route('purchase-orders.store'), [
            'order_number' => 'PO-CROSS',
            'season' => 'V1',
            'collection' => 'C1',
            'release_name' => 'L1',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store2->code, // Outra loja
            'order_date' => '2026-01-15',
        ]);

        $response->assertSessionHasErrors('store_id');
        $this->assertDatabaseMissing('purchase_orders', ['order_number' => 'PO-CROSS']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function createTestOrder(array $overrides = []): PurchaseOrder
    {
        return PurchaseOrder::create(array_merge([
            'order_number' => 'PO-' . uniqid(),
            'season' => 'INVERNO 2026',
            'collection' => 'INVERNO 1',
            'release_name' => 'Lançamento 1',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->code,
            'order_date' => '2026-01-15',
            'status' => PurchaseOrderStatus::PENDING->value,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }
}
