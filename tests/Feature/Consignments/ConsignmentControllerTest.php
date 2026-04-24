<?php

namespace Tests\Feature\Consignments;

use App\Enums\ConsignmentStatus;
use App\Enums\ConsignmentType;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\ConsignmentReturn;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobertura HTTP do ConsignmentController — index, show, store, update,
 * destroy, transition, registerReturn + endpoints de lookup.
 * Valida também middleware permission + tenant.module e store scoping.
 */
class ConsignmentControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Product $product;

    protected ProductVariant $variant;

    protected \App\Models\Movement $movement;

    protected int $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        app(\App\Services\CentralRoleResolver::class)->clearCache();

        MovementType::firstOrCreate(['code' => 20], ['description' => 'Remessa']);
        MovementType::firstOrCreate(['code' => 21], ['description' => 'Retorno']);

        $this->store = Store::factory()->create(['code' => 'Z421']);
        $this->employeeId = $this->createTestEmployee(['store_id' => 'Z421']);

        $this->product = Product::create([
            'reference' => 'REF-001',
            'description' => 'Sandália',
            'sale_price' => 100.00,
            'is_active' => true,
        ]);

        // barcode = concat ref+size (padrão CIGAM);
        // aux_reference = EAN-13 real (opcional)
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'barcode' => 'REF-001U36',
            'aux_reference' => '1234567890123',
            'size_cigam_code' => 'U36',
            'is_active' => true,
        ]);

        // Movement da NF de saída — items da consignação DEVEM vir dela
        $this->movement = \App\Models\Movement::create([
            'store_code' => 'Z421',
            'invoice_number' => '55001',
            'movement_code' => 20,
            'movement_date' => '2026-04-23',
            'movement_time' => '10:00:00',
            'ref_size' => 'REF-001U36',
            'barcode' => '1234567890123',
            'quantity' => 2,
            'net_quantity' => 2,
            'sale_price' => 100.00,
            'realized_value' => 200.00,
            'net_value' => 200.00,
            'discount_value' => 0,
            'entry_exit' => 'S',
            'synced_at' => now(),
        ]);

        config(['queue.default' => 'sync']);
    }

    protected function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => ConsignmentType::CLIENTE->value,
            'store_id' => $this->store->id,
            'employee_id' => $this->employeeId,
            'recipient_name' => 'Maria Cliente',
            'recipient_document' => '123.456.789-09',
            'outbound_invoice_number' => '55001',
            'outbound_invoice_date' => '2026-04-23',
            'items' => [
                [
                    'movement_id' => $this->movement->id,
                    'product_id' => $this->product->id,
                    'product_variant_id' => $this->variant->id,
                    'reference' => 'REF-001',
                    'size_cigam_code' => 'U36',
                    'quantity' => 2,
                    'unit_value' => 100.00,
                ],
            ],
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // index
    // ------------------------------------------------------------------

    public function test_admin_can_view_index(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('consignments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Consignments/Index'));
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->get(route('consignments.index'))->assertRedirect('/login');
    }

    public function test_index_hides_terminal_by_default(): void
    {
        Consignment::factory()->pending()->forStore($this->store)->create();
        Consignment::factory()->completed()->forStore($this->store)->create();
        Consignment::factory()->cancelled()->forStore($this->store)->create();

        $response = $this->actingAs($this->adminUser)->get(route('consignments.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Consignments/Index')
            ->where('consignments.total', 1)
        );
    }

    public function test_index_includes_terminal_when_flag_set(): void
    {
        Consignment::factory()->pending()->forStore($this->store)->create();
        Consignment::factory()->completed()->forStore($this->store)->create();

        $this->actingAs($this->adminUser)
            ->get(route('consignments.index', ['include_terminal' => 1]))
            ->assertInertia(fn ($page) => $page->where('consignments.total', 2));
    }

    public function test_index_search_filters_by_recipient(): void
    {
        Consignment::factory()
            ->pending()
            ->forStore($this->store)
            ->create(['recipient_name' => 'JOÃO SILVA']);

        Consignment::factory()
            ->pending()
            ->forStore($this->store)
            ->create(['recipient_name' => 'MARIA ANTONIETA']);

        $this->actingAs($this->adminUser)
            ->get(route('consignments.index', ['search' => 'JOÃO']))
            ->assertInertia(fn ($page) => $page->where('consignments.total', 1));
    }

    public function test_index_statistics_are_populated(): void
    {
        Consignment::factory()->pending()->forStore($this->store)->create();
        Consignment::factory()->overdue()->forStore($this->store)->create();
        Consignment::factory()->completed()->forStore($this->store)->create();

        $this->actingAs($this->adminUser)
            ->get(route('consignments.index', ['include_terminal' => 1]))
            ->assertInertia(fn ($page) => $page
                ->where('statistics.pending', 1)
                ->where('statistics.overdue', 1)
                ->where('statistics.completed', 1)
            );
    }

    // ------------------------------------------------------------------
    // store
    // ------------------------------------------------------------------

    public function test_store_creates_consignment(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('consignments.store'), $this->validStorePayload());

        $response->assertRedirect();
        $this->assertDatabaseCount('consignments', 1);
        $this->assertDatabaseCount('consignment_items', 1);
    }

    // ------------------------------------------------------------------
    // Hierarquia < 8 não pode alterar prazo de retorno
    // ------------------------------------------------------------------

    public function test_admin_can_edit_return_period_payload(): void
    {
        // ADMIN tem hierarquia 9 >= 8
        $this->actingAs($this->adminUser)
            ->post(route('consignments.store'), $this->validStorePayload([
                'return_period_days' => 15,
            ]))
            ->assertRedirect();

        $this->assertEquals(15, \App\Models\Consignment::first()->return_period_days);
    }

    public function test_regular_user_cannot_override_return_period(): void
    {
        // USER hierarquia 1 < 8 — o valor 30 é ignorado, volta pro default 7
        $this->actingAs($this->regularUser)
            ->post(route('consignments.store'), $this->validStorePayload([
                'return_period_days' => 30,
            ]))
            ->assertRedirect();

        $this->assertEquals(7, \App\Models\Consignment::first()->return_period_days);
    }

    public function test_index_exposes_can_edit_return_period_true_for_admin(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('consignments.index'))
            ->assertInertia(fn ($page) => $page->where('can.edit_return_period', true));
    }

    public function test_index_exposes_can_edit_return_period_false_for_regular_user(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('consignments.index'))
            ->assertInertia(fn ($page) => $page->where('can.edit_return_period', false));
    }

    public function test_store_rejects_without_items(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('consignments.store'), $this->validStorePayload([
                'items' => [],
            ]))
            ->assertSessionHasErrors(['items']);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('consignments.store'), $this->validStorePayload([
                'type' => 'invalid_type',
            ]))
            ->assertSessionHasErrors(['type']);
    }

    public function test_store_requires_authentication(): void
    {
        $this->post(route('consignments.store'), $this->validStorePayload())
            ->assertRedirect('/login');
    }

    // ------------------------------------------------------------------
    // show
    // ------------------------------------------------------------------

    public function test_show_returns_json_with_detailed_consignment(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();
        ConsignmentItem::factory()
            ->forConsignment($c)
            ->forProduct($this->product, $this->variant)
            ->quantity(2)
            ->create();

        $this->actingAs($this->adminUser)
            ->getJson(route('consignments.show', $c->id))
            ->assertOk()
            ->assertJsonStructure([
                'consignment' => [
                    'id', 'uuid', 'type', 'status', 'items', 'returns', 'status_history',
                ],
            ]);
    }

    // ------------------------------------------------------------------
    // transition
    // ------------------------------------------------------------------

    public function test_transition_completes_consignment(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();
        ConsignmentReturn::create([
            'consignment_id' => $c->id,
            'return_invoice_number' => '99999',
            'return_date' => now()->toDateString(),
            'return_store_code' => $this->store->code,
            'returned_quantity' => 1,
            'returned_value' => 0,
            'registered_by_user_id' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('consignments.transition', $c->id), [
                'to_status' => ConsignmentStatus::COMPLETED->value,
                'note' => 'Tudo ok',
            ])
            ->assertRedirect();

        $this->assertEquals(
            ConsignmentStatus::COMPLETED,
            $c->fresh()->status,
        );
    }

    public function test_transition_to_completed_blocked_without_return(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        $this->actingAs($this->adminUser)
            ->post(route('consignments.transition', $c->id), [
                'to_status' => ConsignmentStatus::COMPLETED->value,
                'note' => 'Tudo ok',
            ])
            ->assertSessionHasErrors(['status']);

        $this->assertEquals(
            ConsignmentStatus::PENDING,
            $c->fresh()->status,
        );
    }

    public function test_transition_cancel_without_reason_fails(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        $this->actingAs($this->adminUser)
            ->post(route('consignments.transition', $c->id), [
                'to_status' => ConsignmentStatus::CANCELLED->value,
            ])
            ->assertSessionHasErrors(['note']);
    }

    // ------------------------------------------------------------------
    // registerReturn
    // ------------------------------------------------------------------

    public function test_register_return_full_completes(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();
        $item = ConsignmentItem::factory()
            ->forConsignment($c)
            ->forProduct($this->product, $this->variant)
            ->quantity(2)
            ->create();

        $this->actingAs($this->adminUser)
            ->post(route('consignments.returns.store', $c->id), [
                'return_invoice_number' => '88001',
                'return_date' => '2026-04-23',
                'return_store_code' => 'Z421',
                'items' => [
                    ['consignment_item_id' => $item->id, 'quantity' => 2, 'action' => 'returned'],
                ],
            ])
            ->assertRedirect();

        $this->assertEquals(
            ConsignmentStatus::COMPLETED,
            $c->fresh()->status,
        );
    }

    public function test_register_return_rejects_foreign_item(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();
        $other = Consignment::factory()->pending()->forStore($this->store)->create();
        $otherItem = ConsignmentItem::factory()
            ->forConsignment($other)
            ->forProduct($this->product, $this->variant)
            ->quantity(1)
            ->create();

        $this->actingAs($this->adminUser)
            ->post(route('consignments.returns.store', $c->id), [
                'return_invoice_number' => '88002',
                'return_date' => '2026-04-23',
                'return_store_code' => 'Z421',
                'items' => [
                    ['consignment_item_id' => $otherItem->id, 'quantity' => 1, 'action' => 'returned'],
                ],
            ])
            ->assertSessionHasErrors();
    }

    // ------------------------------------------------------------------
    // destroy
    // ------------------------------------------------------------------

    public function test_destroy_soft_deletes(): void
    {
        $c = Consignment::factory()->draft()->forStore($this->store)->create();

        $this->actingAs($this->adminUser)
            ->delete(route('consignments.destroy', $c->id), [
                'deleted_reason' => 'Erro de cadastro',
            ])
            ->assertRedirect();

        $this->assertNotNull($c->fresh()->deleted_at);
    }

    public function test_destroy_blocked_when_has_returns(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();
        $item = ConsignmentItem::factory()
            ->forConsignment($c)
            ->forProduct($this->product, $this->variant)
            ->quantity(2)
            ->create();

        // Registra um retorno
        app(\App\Services\ConsignmentReturnService::class)->register(
            $c->fresh(),
            [
                'return_invoice_number' => '99001',
                'return_date' => '2026-04-23',
                'return_store_code' => 'Z421',
            ],
            [['consignment_item_id' => $item->id, 'quantity' => 1, 'action' => 'returned']],
            $this->adminUser,
        );

        $this->actingAs($this->adminUser)
            ->delete(route('consignments.destroy', $c->id), ['deleted_reason' => 'tentativa'])
            ->assertSessionHasErrors();
    }

    // ------------------------------------------------------------------
    // Lookups
    // ------------------------------------------------------------------

    public function test_lookup_products_returns_matches(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson(route('consignments.lookup.products', ['q' => 'REF']))
            ->assertOk()
            ->assertJsonStructure(['results' => [['product_id', 'reference', 'variants']]]);
    }

    public function test_lookup_products_rejects_short_query(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson(route('consignments.lookup.products', ['q' => 'R']))
            ->assertStatus(422);
    }

    public function test_lookup_outbound_invoice_populates_items(): void
    {
        Movement::create([
            'store_code' => 'Z421',
            'invoice_number' => '55001',
            'movement_code' => 20,
            'movement_date' => '2026-04-23',
            'movement_time' => '10:00:00',
            'ref_size' => 'REF-001|36',
            'barcode' => '1234567890123',
            'quantity' => 2,
            'net_quantity' => 2,
            'sale_price' => 100,
            'realized_value' => 200,
            'net_value' => 200,
            'discount_value' => 0,
            'entry_exit' => 'S',
            'synced_at' => now(),
        ]);

        $this->actingAs($this->adminUser)
            ->getJson(route('consignments.lookup.outbound-invoice', [
                'invoice_number' => '55001',
                'store_code' => 'Z421',
                'movement_date' => '2026-04-23',
            ]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('items.0.product_id', $this->product->id);
    }

    public function test_lookup_employees_returns_active_employees_of_store(): void
    {
        // Cria funcionários ativos na loja alvo
        $this->createTestEmployee(['store_id' => 'Z421', 'name' => 'Maria Silva', 'cpf' => '11111111111']);
        $this->createTestEmployee(['store_id' => 'Z421', 'name' => 'João Souza', 'cpf' => '22222222222']);
        // E um na outra loja — não deve aparecer
        $otherStore = Store::factory()->create(['code' => 'Z499']);
        $this->createTestEmployee(['store_id' => 'Z499', 'name' => 'Outra Loja', 'cpf' => '33333333333']);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('consignments.lookup.employees', ['store_id' => $this->store->id]))
            ->assertOk();

        $names = collect($response->json('employees'))->pluck('name')->all();
        $this->assertContains('Maria Silva', $names);
        $this->assertContains('João Souza', $names);
        $this->assertNotContains('Outra Loja', $names);
    }

    public function test_lookup_employees_requires_valid_store(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson(route('consignments.lookup.employees', ['store_id' => 99999]))
            ->assertStatus(422);
    }

    public function test_lookup_return_invoice_uses_code_21(): void
    {
        Movement::create([
            'store_code' => 'Z421',
            'invoice_number' => '88001',
            'movement_code' => 21,
            'movement_date' => '2026-04-23',
            'movement_time' => '10:00:00',
            'ref_size' => 'REF-001|36',
            'barcode' => '1234567890123',
            'quantity' => 1,
            'net_quantity' => 1,
            'sale_price' => 100,
            'realized_value' => 100,
            'net_value' => 100,
            'discount_value' => 0,
            'entry_exit' => 'E',
            'synced_at' => now(),
        ]);

        $this->actingAs($this->adminUser)
            ->getJson(route('consignments.lookup.return-invoice', [
                'invoice_number' => '88001',
                'store_code' => 'Z421',
            ]))
            ->assertOk()
            ->assertJsonPath('found', true);
    }
}
