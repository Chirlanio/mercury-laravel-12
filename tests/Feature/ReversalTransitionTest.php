<?php

namespace Tests\Feature;

use App\Enums\ReversalStatus;
use App\Enums\ReversalType;
use App\Events\ReversalStatusChanged;
use App\Models\Reversal;
use App\Models\ReversalReason;
use App\Models\Store;
use App\Services\ReversalTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReversalTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected ReversalReason $reason;

    protected ReversalTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja']);
        $this->reason = ReversalReason::where('code', 'FURO_ESTOQUE')->firstOrFail();
        $this->service = app(ReversalTransitionService::class);
    }

    protected function makeReversal(ReversalStatus $status = ReversalStatus::PENDING_REVERSAL): Reversal
    {
        return Reversal::create([
            'invoice_number' => 'NF-'.rand(1000, 9999),
            'store_code' => $this->store->code,
            'movement_date' => now()->toDateString(),
            'customer_name' => 'Cliente',
            'sale_total' => 500,
            'type' => ReversalType::TOTAL->value,
            'amount_original' => 500,
            'amount_reversal' => 500,
            'status' => $status->value,
            'reversal_reason_id' => $this->reason->id,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Transições válidas (happy paths)
    // ------------------------------------------------------------------

    public function test_pending_reversal_to_pending_authorization(): void
    {
        $reversal = $this->makeReversal(ReversalStatus::PENDING_REVERSAL);

        $updated = $this->service->transition(
            $reversal,
            ReversalStatus::PENDING_AUTHORIZATION,
            $this->adminUser
        );

        $this->assertEquals(ReversalStatus::PENDING_AUTHORIZATION, $updated->status);
        $this->assertDatabaseHas('reversal_status_histories', [
            'reversal_id' => $reversal->id,
            'from_status' => 'pending_reversal',
            'to_status' => 'pending_authorization',
        ]);
    }

    public function test_pending_authorization_to_authorized_sets_authorized_by(): void
    {
        $reversal = $this->makeReversal(ReversalStatus::PENDING_AUTHORIZATION);

        $updated = $this->service->transition(
            $reversal,
            ReversalStatus::AUTHORIZED,
            $this->adminUser
        );

        $this->assertEquals(ReversalStatus::AUTHORIZED, $updated->status);
        $this->assertEquals($this->adminUser->id, $updated->authorized_by_user_id);
    }

    public function test_pending_finance_to_reversed_sets_processed_by_and_reversed_at(): void
    {
        $reversal = $this->makeReversal(ReversalStatus::PENDING_FINANCE);

        $updated = $this->service->transition(
            $reversal,
            ReversalStatus::REVERSED,
            $this->adminUser
        );

        $this->assertEquals(ReversalStatus::REVERSED, $updated->status);
        $this->assertEquals($this->adminUser->id, $updated->processed_by_user_id);
        $this->assertNotNull($updated->reversed_at);
    }

    public function test_transition_to_cancelled_sets_cancelled_at_and_reason(): void
    {
        $reversal = $this->makeReversal(ReversalStatus::PENDING_AUTHORIZATION);

        $updated = $this->service->transition(
            $reversal,
            ReversalStatus::CANCELLED,
            $this->adminUser,
            'Cliente desistiu'
        );

        $this->assertEquals(ReversalStatus::CANCELLED, $updated->status);
        $this->assertNotNull($updated->cancelled_at);
        $this->assertEquals('Cliente desistiu', $updated->cancelled_reason);
    }

    // ------------------------------------------------------------------
    // Transições inválidas
    // ------------------------------------------------------------------

    public function test_cannot_transition_from_terminal_state(): void
    {
        $reversal = $this->makeReversal(ReversalStatus::REVERSED);

        $this->expectException(ValidationException::class);
        $this->service->transition($reversal, ReversalStatus::CANCELLED, $this->adminUser);
    }

    public function test_cannot_skip_states_invalid_transition(): void
    {
        $reversal = $this->makeReversal(ReversalStatus::PENDING_REVERSAL);

        $this->expectException(ValidationException::class);
        // pending_reversal → pending_finance não é permitido
        $this->service->transition($reversal, ReversalStatus::PENDING_FINANCE, $this->adminUser);
    }

    public function test_cancel_requires_note(): void
    {
        $reversal = $this->makeReversal(ReversalStatus::PENDING_AUTHORIZATION);

        $this->expectException(ValidationException::class);
        $this->service->transition($reversal, ReversalStatus::CANCELLED, $this->adminUser, null);
    }

    public function test_deleted_reversal_cannot_transition(): void
    {
        $reversal = $this->makeReversal(ReversalStatus::PENDING_REVERSAL);
        $reversal->update(['deleted_at' => now(), 'deleted_reason' => 'teste']);

        $this->expectException(ValidationException::class);
        $this->service->transition($reversal, ReversalStatus::PENDING_AUTHORIZATION, $this->adminUser);
    }

    // ------------------------------------------------------------------
    // Permissões por transição
    // ------------------------------------------------------------------

    public function test_user_without_approve_permission_cannot_authorize(): void
    {
        $this->regularUser->update(['access_level_id' => 4]); // user sem APPROVE_REVERSALS
        $reversal = $this->makeReversal(ReversalStatus::PENDING_AUTHORIZATION);

        $this->expectException(ValidationException::class);
        $this->service->transition($reversal, ReversalStatus::AUTHORIZED, $this->regularUser);
    }

    public function test_user_without_process_permission_cannot_mark_reversed(): void
    {
        $reversal = $this->makeReversal(ReversalStatus::PENDING_FINANCE);

        $this->expectException(ValidationException::class);
        $this->service->transition($reversal, ReversalStatus::REVERSED, $this->regularUser);
    }

    // ------------------------------------------------------------------
    // Evento + Histórico
    // ------------------------------------------------------------------

    public function test_transition_dispatches_event(): void
    {
        Event::fake();
        $reversal = $this->makeReversal(ReversalStatus::PENDING_REVERSAL);

        $this->service->transition(
            $reversal,
            ReversalStatus::PENDING_AUTHORIZATION,
            $this->adminUser
        );

        Event::assertDispatched(ReversalStatusChanged::class, function ($e) use ($reversal) {
            return $e->reversal->id === $reversal->id
                && $e->fromStatus === ReversalStatus::PENDING_REVERSAL
                && $e->toStatus === ReversalStatus::PENDING_AUTHORIZATION;
        });
    }

    public function test_each_transition_creates_history_row(): void
    {
        $reversal = $this->makeReversal(ReversalStatus::PENDING_REVERSAL);

        $this->service->transition($reversal, ReversalStatus::PENDING_AUTHORIZATION, $this->adminUser);
        $this->service->transition($reversal->fresh(), ReversalStatus::AUTHORIZED, $this->adminUser);
        $this->service->transition($reversal->fresh(), ReversalStatus::PENDING_FINANCE, $this->adminUser);
        $this->service->transition($reversal->fresh(), ReversalStatus::REVERSED, $this->adminUser);

        $this->assertEquals(4, $reversal->statusHistory()->count());
    }
}
