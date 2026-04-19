<?php

namespace Tests\Feature;

use App\Enums\ReturnReasonCategory;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Models\ReturnOrder;
use App\Models\ReturnReason;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReturnOrderControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Store $otherStore;

    protected ReturnReason $reason;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z441', 'name' => 'E-commerce']);
        $this->otherStore = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Física']);

        $this->reason = ReturnReason::where('code', 'ARREPEND_GERAL')->firstOrFail();
    }

    protected function createSale(string $invoice, string $storeCode = 'Z441', float $total = 200.00): int
    {
        return DB::table('movements')->insertGetId([
            'movement_date' => now()->toDateString(),
            'store_code' => $storeCode,
            'cpf_customer' => '12345678900',
            'invoice_number' => $invoice,
            'movement_code' => 2,
            'cpf_consultant' => '98765432100',
            'ref_size' => 'REF1|M',
            'barcode' => '2000000000011',
            'sale_price' => $total,
            'cost_price' => $total / 2,
            'realized_value' => $total,
            'discount_value' => 0,
            'quantity' => 1,
            'entry_exit' => 'S',
            'net_value' => $total,
            'net_quantity' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createReturn(array $overrides = []): ReturnOrder
    {
        return ReturnOrder::create(array_merge([
            'invoice_number' => 'NF-'.rand(1000, 9999),
            'store_code' => $this->store->code,
            'movement_date' => now()->toDateString(),
            'customer_name' => 'Cliente E-commerce',
            'sale_total' => 200,
            'type' => ReturnType::TROCA->value,
            'amount_items' => 200,
            'status' => ReturnStatus::PENDING->value,
            'reason_category' => ReturnReasonCategory::ARREPENDIMENTO->value,
            'return_reason_id' => $this->reason->id,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('returns.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Returns/Index'));
    }

    public function test_regular_user_cannot_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('returns.index'));
        $response->assertStatus(403);
    }

    public function test_index_hides_terminal_states_by_default(): void
    {
        $this->createReturn(['invoice_number' => 'A', 'status' => ReturnStatus::PENDING->value]);
        $this->createReturn(['invoice_number' => 'B', 'status' => ReturnStatus::COMPLETED->value]);

        $response = $this->actingAs($this->adminUser)->get(route('returns.index'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page->has('returns.data', 1)->where('returns.data.0.invoice_number', 'A')
        );
    }

    public function test_index_can_include_terminal_with_flag(): void
    {
        $this->createReturn(['invoice_number' => 'A', 'status' => ReturnStatus::PENDING->value]);
        $this->createReturn(['invoice_number' => 'B', 'status' => ReturnStatus::COMPLETED->value]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('returns.index', ['include_terminal' => 1]));

        $response->assertInertia(fn ($page) => $page->has('returns.data', 2));
    }

    public function test_admin_can_create_troca_with_valid_nf(): void
    {
        $movementId = $this->createSale('NF-TROCA', 'Z441', 300.00);

        $response = $this->actingAs($this->adminUser)->post(route('returns.store'), [
            'invoice_number' => 'NF-TROCA',
            'customer_name' => 'Cliente Teste',
            'type' => 'troca',
            'reason_category' => ReturnReasonCategory::TAMANHO_COR->value,
            'items' => [['movement_id' => $movementId]],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('return_orders', [
            'invoice_number' => 'NF-TROCA',
            'store_code' => 'Z441',
            'type' => 'troca',
            'status' => 'pending',
        ]);
    }

    public function test_create_fails_when_nf_not_found(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('returns.store'), [
            'invoice_number' => 'NF-NAOEXISTE',
            'customer_name' => 'Cliente',
            'type' => 'troca',
            'reason_category' => ReturnReasonCategory::ARREPENDIMENTO->value,
            'items' => [['movement_id' => 999999]],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_create_estorno_requires_refund_amount(): void
    {
        $movementId = $this->createSale('NF-EST', 'Z441', 500);

        $response = $this->actingAs($this->adminUser)->post(route('returns.store'), [
            'invoice_number' => 'NF-EST',
            'customer_name' => 'Cliente',
            'type' => 'estorno',
            // refund_amount omitido — deve falhar
            'reason_category' => ReturnReasonCategory::DEFEITO->value,
            'items' => [['movement_id' => $movementId]],
        ]);

        $response->assertSessionHasErrors('refund_amount');
    }

    public function test_create_blocks_duplicate_same_type(): void
    {
        $movementId = $this->createSale('NF-DUP', 'Z441', 100);

        $this->actingAs($this->adminUser)->post(route('returns.store'), [
            'invoice_number' => 'NF-DUP',
            'customer_name' => 'Cliente 1',
            'type' => 'troca',
            'reason_category' => ReturnReasonCategory::ARREPENDIMENTO->value,
            'items' => [['movement_id' => $movementId]],
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('returns.store'), [
            'invoice_number' => 'NF-DUP',
            'customer_name' => 'Cliente Dup',
            'type' => 'troca',
            'reason_category' => ReturnReasonCategory::ARREPENDIMENTO->value,
            'items' => [['movement_id' => $movementId]],
        ]);

        $response->assertSessionHasErrors('invoice_number');
        $this->assertEquals(1, ReturnOrder::count());
    }

    public function test_show_returns_json(): void
    {
        $r = $this->createReturn(['invoice_number' => 'NF-SHOW']);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('returns.show', $r->id));

        $response->assertStatus(200);
        $response->assertJsonPath('return.id', $r->id);
        $response->assertJsonPath('return.invoice_number', 'NF-SHOW');
    }

    public function test_update_allowed_in_early_states(): void
    {
        $r = $this->createReturn(['status' => ReturnStatus::PENDING->value]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('returns.update', $r->id), [
                'customer_name' => 'Novo Nome',
                'reason_category' => ReturnReasonCategory::DEFEITO->value,
                'notes' => 'Atualizado',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('return_orders', [
            'id' => $r->id,
            'customer_name' => 'Novo Nome',
            'notes' => 'Atualizado',
        ]);
    }

    public function test_update_blocked_in_terminal_state(): void
    {
        $r = $this->createReturn(['status' => ReturnStatus::COMPLETED->value]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('returns.update', $r->id), [
                'customer_name' => 'Tentativa',
            ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_admin_can_soft_delete_with_reason(): void
    {
        $r = $this->createReturn();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('returns.destroy', $r->id), [
                'deleted_reason' => 'Criado por engano',
            ]);

        $response->assertRedirect();
        $this->assertNotNull($r->fresh()->deleted_at);
    }

    public function test_cannot_delete_completed(): void
    {
        $r = $this->createReturn(['status' => ReturnStatus::COMPLETED->value]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('returns.destroy', $r->id), [
                'deleted_reason' => 'Tentativa',
            ]);

        $response->assertSessionHasErrors();
        $this->assertNull($r->fresh()->deleted_at);
    }

    public function test_support_user_scoped_to_own_store(): void
    {
        $this->supportUser->update(['store_id' => $this->store->code]);

        $this->createReturn(['invoice_number' => 'OWN', 'store_code' => $this->store->code]);
        $this->createReturn(['invoice_number' => 'OTHER', 'store_code' => $this->otherStore->code]);

        $response = $this->actingAs($this->supportUser)->get(route('returns.index'));

        $response->assertInertia(
            fn ($page) => $page
                ->has('returns.data', 1)
                ->where('returns.data.0.invoice_number', 'OWN')
                ->where('isStoreScoped', true)
        );
    }

    public function test_statistics_endpoint_returns_aggregates(): void
    {
        $this->createReturn(['status' => ReturnStatus::PENDING->value, 'type' => 'troca']);
        $this->createReturn([
            'invoice_number' => 'X',
            'status' => ReturnStatus::COMPLETED->value,
            'type' => 'estorno',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('returns.statistics'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total', 'pending_approval', 'awaiting_product', 'processing',
            'completed_this_month_amount', 'cancelled', 'trocas', 'estornos', 'creditos',
        ]);
        $response->assertJsonPath('pending_approval', 1);
        $response->assertJsonPath('trocas', 1);
        $response->assertJsonPath('estornos', 1);
    }
}
