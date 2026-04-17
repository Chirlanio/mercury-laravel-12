<?php

namespace Tests\Feature;

use App\Enums\ReversalStatus;
use App\Enums\ReversalType;
use App\Models\Movement;
use App\Models\Reversal;
use App\Models\ReversalReason;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReversalControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Store $store2;

    protected ReversalReason $reason;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
        $this->store2 = Store::factory()->create(['code' => 'Z425', 'name' => 'Loja Secundária']);

        // A migration cria os motivos padrão — apenas recuperamos aqui.
        $this->reason = ReversalReason::where('code', 'FURO_ESTOQUE')->firstOrFail();
    }

    /**
     * Cria uma venda (movement_code=2) sintética para servir de base
     * ao lookup de NF dos testes.
     */
    protected function createSale(string $invoiceNumber, string $storeCode = 'Z424', float $total = 500.00, ?string $date = null): void
    {
        DB::table('movements')->insert([
            'movement_date' => $date ?? now()->toDateString(),
            'store_code' => $storeCode,
            'cpf_customer' => '12345678900',
            'invoice_number' => $invoiceNumber,
            'movement_code' => 2,
            'cpf_consultant' => '98765432100',
            'barcode' => '2000000000001',
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

    protected function createReversal(array $overrides = []): Reversal
    {
        return Reversal::create(array_merge([
            'invoice_number' => 'NF-'.rand(1000, 9999),
            'store_code' => $this->store->code,
            'movement_date' => now()->toDateString(),
            'customer_name' => 'Cliente Teste',
            'sale_total' => 500,
            'type' => ReversalType::TOTAL->value,
            'amount_original' => 500,
            'amount_reversal' => 500,
            'status' => ReversalStatus::PENDING_REVERSAL->value,
            'reversal_reason_id' => $this->reason->id,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Index
    // ------------------------------------------------------------------

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('reversals.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Reversals/Index'));
    }

    public function test_regular_user_cannot_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('reversals.index'));
        $response->assertStatus(403);
    }

    public function test_index_hides_terminal_states_by_default(): void
    {
        $this->createReversal([
            'invoice_number' => 'A',
            'status' => ReversalStatus::PENDING_REVERSAL->value,
        ]);
        $this->createReversal([
            'invoice_number' => 'B',
            'status' => ReversalStatus::REVERSED->value,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reversals.index'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('Reversals/Index')
                ->has('reversals.data', 1)
                ->where('reversals.data.0.invoice_number', 'A')
        );
    }

    public function test_index_can_include_terminal_with_flag(): void
    {
        $this->createReversal([
            'invoice_number' => 'A',
            'status' => ReversalStatus::PENDING_REVERSAL->value,
        ]);
        $this->createReversal([
            'invoice_number' => 'B',
            'status' => ReversalStatus::REVERSED->value,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('reversals.index', ['include_terminal' => 1]));

        $response->assertInertia(fn ($page) => $page->has('reversals.data', 2));
    }

    // ------------------------------------------------------------------
    // Store (create)
    // ------------------------------------------------------------------

    public function test_admin_can_create_reversal_with_valid_nf(): void
    {
        $this->createSale('NF-12345', 'Z424', 500);

        $response = $this->actingAs($this->adminUser)->post(route('reversals.store'), [
            'store_code' => 'Z424',
            'invoice_number' => 'NF-12345',
            'customer_name' => 'Cliente Teste',
            'type' => 'total',
            'reversal_reason_id' => $this->reason->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('reversals', [
            'invoice_number' => 'NF-12345',
            'store_code' => 'Z424',
            'customer_name' => 'Cliente Teste',
            'status' => 'pending_reversal',
            'type' => 'total',
        ]);
    }

    public function test_create_fails_without_store_code(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('reversals.store'), [
            'invoice_number' => 'NF-12345',
            'customer_name' => 'Cliente Teste',
            'type' => 'total',
            'reversal_reason_id' => $this->reason->id,
        ]);

        $response->assertSessionHasErrors('store_code');
    }

    public function test_create_fails_when_nf_not_found_in_movements(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('reversals.store'), [
            'store_code' => 'Z424',
            'invoice_number' => 'NF-NAO-EXISTE',
            'customer_name' => 'Cliente Teste',
            'type' => 'total',
            'reversal_reason_id' => $this->reason->id,
        ]);

        $response->assertSessionHasErrors('invoice_number');
    }

    public function test_create_blocks_duplicate_reversal_for_same_nf_store_value(): void
    {
        $this->createSale('NF-DUP', 'Z424', 500);

        $this->actingAs($this->adminUser)->post(route('reversals.store'), [
            'store_code' => 'Z424',
            'invoice_number' => 'NF-DUP',
            'customer_name' => 'Cliente 1',
            'type' => 'total',
            'reversal_reason_id' => $this->reason->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('reversals.store'), [
            'store_code' => 'Z424',
            'invoice_number' => 'NF-DUP',
            'customer_name' => 'Cliente Dup',
            'type' => 'total',
            'reversal_reason_id' => $this->reason->id,
        ]);

        $response->assertSessionHasErrors('invoice_number');
        $this->assertEquals(1, Reversal::count());
    }

    public function test_partial_by_value_calculates_correct_amount(): void
    {
        $this->createSale('NF-PARCIAL', 'Z424', 500);

        $this->actingAs($this->adminUser)->post(route('reversals.store'), [
            'store_code' => 'Z424',
            'invoice_number' => 'NF-PARCIAL',
            'customer_name' => 'Cliente',
            'type' => 'partial',
            'partial_mode' => 'by_value',
            'amount_correct' => 400,
            'reversal_reason_id' => $this->reason->id,
        ]);

        $this->assertDatabaseHas('reversals', [
            'invoice_number' => 'NF-PARCIAL',
            'amount_original' => 500,
            'amount_correct' => 400,
            'amount_reversal' => 100,
        ]);
    }

    // ------------------------------------------------------------------
    // Show
    // ------------------------------------------------------------------

    public function test_show_returns_json_with_reversal(): void
    {
        $reversal = $this->createReversal(['invoice_number' => 'NF-SHOW']);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('reversals.show', $reversal->id));

        $response->assertStatus(200);
        $response->assertJsonPath('reversal.id', $reversal->id);
        $response->assertJsonPath('reversal.invoice_number', 'NF-SHOW');
    }

    // ------------------------------------------------------------------
    // Update
    // ------------------------------------------------------------------

    public function test_update_allowed_in_early_states(): void
    {
        $reversal = $this->createReversal(['status' => ReversalStatus::PENDING_REVERSAL->value]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('reversals.update', $reversal->id), [
                'customer_name' => 'Novo Nome',
                'reversal_reason_id' => $this->reason->id,
                'notes' => 'Atualizado',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reversals', [
            'id' => $reversal->id,
            'customer_name' => 'Novo Nome',
            'notes' => 'Atualizado',
        ]);
    }

    public function test_update_blocked_in_terminal_state(): void
    {
        $reversal = $this->createReversal(['status' => ReversalStatus::REVERSED->value]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('reversals.update', $reversal->id), [
                'customer_name' => 'Tentativa',
                'reversal_reason_id' => $this->reason->id,
            ]);

        $response->assertSessionHasErrors('status');
    }

    // ------------------------------------------------------------------
    // Destroy (soft delete)
    // ------------------------------------------------------------------

    public function test_admin_can_soft_delete_with_reason(): void
    {
        $reversal = $this->createReversal();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('reversals.destroy', $reversal->id), [
                'deleted_reason' => 'Criado por engano',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reversals', [
            'id' => $reversal->id,
            'deleted_reason' => 'Criado por engano',
        ]);
        $this->assertNotNull($reversal->fresh()->deleted_at);
    }

    public function test_destroy_requires_reason(): void
    {
        $reversal = $this->createReversal();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('reversals.destroy', $reversal->id), []);

        $response->assertSessionHasErrors('deleted_reason');
    }

    public function test_cannot_delete_reversed(): void
    {
        $reversal = $this->createReversal(['status' => ReversalStatus::REVERSED->value]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('reversals.destroy', $reversal->id), [
                'deleted_reason' => 'Tentativa',
            ]);

        $response->assertSessionHasErrors();
        $this->assertNull($reversal->fresh()->deleted_at);
    }

    // ------------------------------------------------------------------
    // Store scoping (SUPPORT só vê a própria loja)
    // ------------------------------------------------------------------

    public function test_support_user_scoped_to_own_store(): void
    {
        $this->supportUser->update(['store_id' => $this->store->code]);

        $this->createReversal(['invoice_number' => 'OWN', 'store_code' => $this->store->code]);
        $this->createReversal(['invoice_number' => 'OTHER', 'store_code' => $this->store2->code]);

        $response = $this->actingAs($this->supportUser)->get(route('reversals.index'));

        $response->assertInertia(
            fn ($page) => $page
                ->has('reversals.data', 1)
                ->where('reversals.data.0.invoice_number', 'OWN')
                ->where('isStoreScoped', true)
        );
    }

    public function test_support_user_blocked_from_other_store_detail(): void
    {
        $this->supportUser->update(['store_id' => $this->store->code]);
        $reversal = $this->createReversal(['store_code' => $this->store2->code]);

        $response = $this->actingAs($this->supportUser)
            ->getJson(route('reversals.show', $reversal->id));

        $response->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Statistics
    // ------------------------------------------------------------------

    public function test_statistics_endpoint_returns_aggregates(): void
    {
        $this->createReversal(['status' => ReversalStatus::PENDING_AUTHORIZATION->value]);
        $this->createReversal(['status' => ReversalStatus::REVERSED->value, 'invoice_number' => 'X', 'reversed_at' => now()]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('reversals.statistics'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total',
            'total_amount',
            'pending_approval',
            'pending_finance',
            'reversed_this_month_amount',
        ]);
        $response->assertJsonPath('pending_approval', 1);
    }
}
