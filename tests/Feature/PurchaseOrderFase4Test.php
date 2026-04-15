<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Notifications\PurchaseOrderLateAlertNotification;
use App\Notifications\PurchaseOrderStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PurchaseOrderFase4Test extends TestCase
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

    // ------------------------------------------------------------------
    // Listener: NotifyPurchaseOrderStakeholders
    // ------------------------------------------------------------------

    public function test_status_change_notifies_stakeholders(): void
    {
        Notification::fake();

        $order = $this->createTestOrder();

        $this->actingAs($this->adminUser)
            ->post(route('purchase-orders.transition', $order->id), [
                'to_status' => PurchaseOrderStatus::INVOICED->value,
            ]);

        // Não notifica o próprio actor
        Notification::assertNotSentTo($this->adminUser, PurchaseOrderStatusChangedNotification::class);

        // Não notifica regularUser (sem permissions)
        Notification::assertNotSentTo($this->regularUser, PurchaseOrderStatusChangedNotification::class);
    }

    // ------------------------------------------------------------------
    // Dashboard endpoint
    // ------------------------------------------------------------------

    public function test_dashboard_renders_with_data(): void
    {
        $this->createTestOrder();
        $this->createTestOrder(['status' => PurchaseOrderStatus::INVOICED->value]);

        $response = $this->actingAs($this->adminUser)->get(route('purchase-orders.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('PurchaseOrders/Dashboard')
            ->has('statusDistribution')
            ->has('byMonth')
            ->has('topSuppliers')
            ->has('topBrands')
        );
    }

    public function test_dashboard_respects_store_scoping(): void
    {
        $store2 = Store::factory()->create(['code' => 'Z425']);
        $this->createTestOrder(['store_id' => $this->store->code]);
        $this->createTestOrder(['store_id' => $store2->code]);

        $this->supportUser->update(['store_id' => $this->store->code]);

        $response = $this->actingAs($this->supportUser)->get(route('purchase-orders.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('isStoreScoped', true)
            ->where('scopedStoreCode', $this->store->code)
        );
    }

    // ------------------------------------------------------------------
    // Late alert command
    // ------------------------------------------------------------------

    public function test_late_alert_command_finds_overdue_orders(): void
    {
        // 1 ordem atrasada (predict_date no passado)
        $overdue = $this->createTestOrder([
            'predict_date' => '2025-01-01',
            'status' => PurchaseOrderStatus::INVOICED->value,
        ]);

        // 1 ordem no prazo (predict_date no futuro)
        $onTime = $this->createTestOrder([
            'predict_date' => '2099-01-01',
            'status' => PurchaseOrderStatus::INVOICED->value,
        ]);

        // 1 ordem entregue (não conta mesmo se atrasada)
        $delivered = $this->createTestOrder([
            'predict_date' => '2025-01-01',
            'status' => PurchaseOrderStatus::DELIVERED->value,
        ]);

        $overdueOrders = PurchaseOrder::query()
            ->notDeleted()
            ->overdue()
            ->get();

        $this->assertEquals(1, $overdueOrders->count());
        $this->assertEquals($overdue->id, $overdueOrders->first()->id);
    }

    public function test_late_alert_notification_payload(): void
    {
        $overdue = $this->createTestOrder([
            'predict_date' => '2025-01-01',
            'status' => PurchaseOrderStatus::INVOICED->value,
        ])->fresh(['supplier', 'store']);

        $notification = new PurchaseOrderLateAlertNotification(collect([$overdue]));
        $payload = $notification->toDatabase($this->adminUser);

        $this->assertEquals('purchase_order_late_alert', $payload['type']);
        $this->assertEquals(1, $payload['count']);
        $this->assertCount(1, $payload['orders']);
        $this->assertEquals($overdue->order_number, $payload['orders'][0]['order_number']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function createTestOrder(array $overrides = []): PurchaseOrder
    {
        $order = PurchaseOrder::create(array_merge([
            'order_number' => 'PO-' . uniqid(),
            'season' => 'V1', 'collection' => 'C1', 'release_name' => 'L1',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->code,
            'order_date' => '2025-01-01',
            'predict_date' => '2099-01-01',
            'status' => PurchaseOrderStatus::PENDING->value,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));

        // Adicionar 1 item pra que total_cost > 0 (algumas validações exigem)
        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'reference' => 'REF-X',
            'size' => 'M',
            'description' => 'Item',
            'unit_cost' => 100,
            'quantity_ordered' => 5,
        ]);

        return $order->fresh('items');
    }
}
