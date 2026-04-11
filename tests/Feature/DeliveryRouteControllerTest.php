<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\DeliveryRouteItem;
use App\Models\Driver;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DeliveryRouteControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
        $this->driver = Driver::create(['name' => 'Zé Delivery', 'is_active' => true]);
    }

    private function createDelivery(array $overrides = []): Delivery
    {
        return Delivery::create(array_merge([
            'store_id' => $this->store->code,
            'client_name' => 'Cliente Teste',
            'invoice_number' => 'NF-'.rand(100, 999),
            'address' => 'Rua Teste, 100',
            'sale_value' => 100.00,
            'payment_method' => 'PIX',
            'installments' => 1,
            'products_qty' => 1,
            'status' => Delivery::STATUS_REQUESTED,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    private function createRoute(array $deliveryIds = []): DeliveryRoute
    {
        $route = DeliveryRoute::create([
            'route_number' => DeliveryRoute::generateRouteNumber(now()->format('Y-m-d')),
            'driver_id' => $this->driver->id,
            'date_route' => now()->format('Y-m-d'),
            'status' => DeliveryRoute::STATUS_PENDING,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        foreach ($deliveryIds as $i => $deliveryId) {
            $delivery = Delivery::find($deliveryId);
            DeliveryRouteItem::create([
                'route_id' => $route->id,
                'delivery_id' => $deliveryId,
                'sequence_order' => $i,
                'client_name' => $delivery->client_name,
                'address' => $delivery->address,
                'created_at' => now(),
            ]);
        }

        return $route;
    }

    public function test_admin_can_view_routes_index(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('delivery-routes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('DeliveryRoutes/Index'));
    }

    public function test_admin_can_create_route(): void
    {
        $d1 = $this->createDelivery(['client_name' => 'Cliente A']);
        $d2 = $this->createDelivery(['client_name' => 'Cliente B']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('delivery-routes.store'), [
                'driver_id' => $this->driver->id,
                'date_route' => now()->format('Y-m-d'),
                'delivery_ids' => [$d1->id, $d2->id],
                'notes' => 'Rota de teste',
            ]);

        $response->assertRedirect(route('delivery-routes.index'));
        $this->assertDatabaseCount('delivery_routes', 1);
        $this->assertDatabaseCount('delivery_route_items', 2);
    }

    public function test_route_number_auto_generated(): void
    {
        $d = $this->createDelivery();

        $this->actingAs($this->adminUser)
            ->post(route('delivery-routes.store'), [
                'driver_id' => $this->driver->id,
                'date_route' => '2026-04-12',
                'delivery_ids' => [$d->id],
            ]);

        $route = DeliveryRoute::first();
        $this->assertStringStartsWith('RT-20260412-', $route->route_number);
    }

    public function test_can_view_route_detail(): void
    {
        $d = $this->createDelivery();
        $route = $this->createRoute([$d->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('delivery-routes.show', $route));

        $response->assertOk();
        $response->assertJsonPath('route.route_number', $route->route_number);
        $response->assertJsonCount(1, 'route.items');
    }

    public function test_can_start_route(): void
    {
        $d = $this->createDelivery();
        $route = $this->createRoute([$d->id]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('delivery-routes.start', $route));

        $response->assertOk();
        $this->assertEquals(DeliveryRoute::STATUS_IN_ROUTE, $route->fresh()->status);
        $this->assertEquals(Delivery::STATUS_IN_ROUTE, $d->fresh()->status);
    }

    public function test_cannot_start_completed_route(): void
    {
        $route = DeliveryRoute::create([
            'route_number' => 'RT-20260412-001',
            'driver_id' => $this->driver->id,
            'date_route' => now(),
            'status' => DeliveryRoute::STATUS_COMPLETED,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('delivery-routes.start', $route));

        $response->assertStatus(422);
    }

    public function test_can_complete_item(): void
    {
        $d = $this->createDelivery();
        $route = $this->createRoute([$d->id]);
        $route->update(['status' => DeliveryRoute::STATUS_IN_ROUTE]);
        $d->update(['status' => Delivery::STATUS_IN_ROUTE]);

        $item = $route->items()->first();

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('delivery-routes.complete-item', ['deliveryRoute' => $route->id, 'item' => $item->id]), [
                'status' => 'delivered',
                'received_by' => 'José',
            ]);

        $response->assertOk();
        $this->assertNotNull($item->fresh()->delivered_at);
        $this->assertEquals(Delivery::STATUS_DELIVERED, $d->fresh()->status);
    }

    public function test_route_auto_completes_when_all_items_delivered(): void
    {
        $d = $this->createDelivery();
        $route = $this->createRoute([$d->id]);
        $route->update(['status' => DeliveryRoute::STATUS_IN_ROUTE]);
        $d->update(['status' => Delivery::STATUS_IN_ROUTE]);

        $item = $route->items()->first();

        $this->actingAs($this->adminUser)
            ->postJson(route('delivery-routes.complete-item', ['deliveryRoute' => $route->id, 'item' => $item->id]), [
                'status' => 'delivered',
            ]);

        $this->assertEquals(DeliveryRoute::STATUS_COMPLETED, $route->fresh()->status);
    }

    public function test_can_cancel_route(): void
    {
        $route = $this->createRoute([]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('delivery-routes.cancel', $route));

        $response->assertOk();
        $this->assertEquals(DeliveryRoute::STATUS_CANCELLED, $route->fresh()->status);
    }

    public function test_can_access_driver_dashboard(): void
    {
        $this->driver->update(['user_id' => $this->adminUser->id]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('driver-dashboard.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('DeliveryRoutes/DriverDashboard'));
    }

    public function test_driver_dashboard_without_driver_shows_empty(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('driver-dashboard.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('route', null));
    }
}
