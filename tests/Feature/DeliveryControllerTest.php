<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DeliveryControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
    }

    private function createDelivery(array $overrides = []): Delivery
    {
        return Delivery::create(array_merge([
            'store_id' => $this->store->code,
            'client_name' => 'Maria Silva',
            'invoice_number' => 'NF-001',
            'address' => 'Rua Teste, 123',
            'neighborhood' => 'Centro',
            'contact_phone' => '11999999999',
            'sale_value' => 150.00,
            'payment_method' => 'PIX',
            'installments' => 1,
            'products_qty' => 2,
            'status' => Delivery::STATUS_REQUESTED,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    public function test_admin_can_view_index(): void
    {
        $this->createDelivery();

        $response = $this->actingAs($this->adminUser)
            ->get(route('deliveries.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Deliveries/Index')
            ->has('deliveries.data', 1));
    }

    public function test_regular_user_can_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('deliveries.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_delivery(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('deliveries.store'), [
                'store_id' => $this->store->code,
                'client_name' => 'João Cliente',
                'invoice_number' => 'NF-002',
                'address' => 'Av. Brasil, 456',
                'contact_phone' => '11888888888',
                'sale_value' => 299.90,
                'payment_method' => 'Cartão',
                'installments' => 3,
                'products_qty' => 1,
            ]);

        $response->assertRedirect(route('deliveries.index'));
        $this->assertDatabaseHas('deliveries', ['client_name' => 'João Cliente']);
    }

    public function test_validation_requires_client_name(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('deliveries.store'), [
                'store_id' => $this->store->code,
            ]);

        $response->assertSessionHasErrors('client_name');
    }

    public function test_admin_can_view_delivery_detail(): void
    {
        $delivery = $this->createDelivery();

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('deliveries.show', $delivery));

        $response->assertOk();
        $response->assertJsonPath('delivery.client_name', 'Maria Silva');
    }

    public function test_admin_can_update_delivery(): void
    {
        $delivery = $this->createDelivery();

        $response = $this->actingAs($this->adminUser)
            ->put(route('deliveries.update', $delivery), [
                'client_name' => 'Maria Atualizada',
                'invoice_number' => 'NF-001',
                'address' => 'Rua Nova, 789',
                'sale_value' => 150,
                'payment_method' => 'PIX',
                'installments' => 1,
                'products_qty' => 2,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('deliveries', ['client_name' => 'Maria Atualizada']);
    }

    public function test_cannot_update_delivered_delivery(): void
    {
        $delivery = $this->createDelivery(['status' => Delivery::STATUS_DELIVERED]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('deliveries.update', $delivery), [
                'client_name' => 'Tentativa',
                'invoice_number' => 'NF-X',
                'sale_value' => 100,
                'payment_method' => 'Dinheiro',
                'installments' => 1,
                'products_qty' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('deliveries', ['client_name' => 'Tentativa']);
    }

    public function test_admin_can_soft_delete_delivery(): void
    {
        $delivery = $this->createDelivery();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('deliveries.destroy', $delivery));

        $response->assertRedirect();
        $this->assertNotNull($delivery->fresh()->deleted_at);
    }

    public function test_can_transition_status(): void
    {
        $delivery = $this->createDelivery();

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('deliveries.status', $delivery), [
                'status' => Delivery::STATUS_COLLECTED,
            ]);

        $response->assertOk();
        $this->assertEquals(Delivery::STATUS_COLLECTED, $delivery->fresh()->status);
    }

    public function test_cannot_invalid_transition(): void
    {
        $delivery = $this->createDelivery(['status' => Delivery::STATUS_DELIVERED]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('deliveries.status', $delivery), [
                'status' => Delivery::STATUS_REQUESTED,
            ]);

        $response->assertStatus(422);
    }

    public function test_can_get_statistics(): void
    {
        $this->createDelivery();
        $this->createDelivery(['status' => Delivery::STATUS_DELIVERED]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('deliveries.statistics'));

        $response->assertOk();
        $response->assertJsonPath('total', 2);
    }

    public function test_can_filter_by_status(): void
    {
        $this->createDelivery();
        $this->createDelivery(['status' => Delivery::STATUS_DELIVERED]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('deliveries.index', ['status' => 'delivered']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('deliveries.data', 1));
    }

    public function test_can_filter_by_store(): void
    {
        $store2 = Store::factory()->create(['code' => 'Z425', 'name' => 'Loja 2']);
        $this->createDelivery();
        $this->createDelivery(['store_id' => $store2->code]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('deliveries.index', ['store_id' => $this->store->code]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('deliveries.data', 1));
    }

    public function test_can_search_by_client_name(): void
    {
        $this->createDelivery(['client_name' => 'Ana Teste']);
        $this->createDelivery(['client_name' => 'Pedro Outro']);

        $response = $this->actingAs($this->adminUser)
            ->get(route('deliveries.index', ['search' => 'Ana']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('deliveries.data', 1));
    }
}
