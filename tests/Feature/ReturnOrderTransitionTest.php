<?php

namespace Tests\Feature;

use App\Enums\ReturnReasonCategory;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Events\ReturnOrderStatusChanged;
use App\Models\ReturnOrder;
use App\Models\ReturnReason;
use App\Models\Store;
use App\Services\ReturnOrderTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReturnOrderTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected ReturnReason $reason;

    protected ReturnOrderTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z441', 'name' => 'E-commerce']);
        $this->reason = ReturnReason::where('code', 'ARREPEND_GERAL')->firstOrFail();
        $this->service = app(ReturnOrderTransitionService::class);
    }

    protected function makeReturn(ReturnStatus $status = ReturnStatus::PENDING): ReturnOrder
    {
        return ReturnOrder::create([
            'invoice_number' => 'NF-'.rand(1000, 9999),
            'store_code' => $this->store->code,
            'movement_date' => now()->toDateString(),
            'customer_name' => 'Cliente',
            'sale_total' => 200,
            'type' => ReturnType::TROCA->value,
            'amount_items' => 200,
            'status' => $status->value,
            'reason_category' => ReturnReasonCategory::ARREPENDIMENTO->value,
            'return_reason_id' => $this->reason->id,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    public function test_pending_to_approved_sets_approved_by_and_approved_at(): void
    {
        $r = $this->makeReturn(ReturnStatus::PENDING);

        $updated = $this->service->transition($r, ReturnStatus::APPROVED, $this->adminUser);

        $this->assertEquals(ReturnStatus::APPROVED, $updated->status);
        $this->assertEquals($this->adminUser->id, $updated->approved_by_user_id);
        $this->assertNotNull($updated->approved_at);
    }

    public function test_awaiting_product_to_completed_sets_processed_by_and_completed_at(): void
    {
        $r = $this->makeReturn(ReturnStatus::AWAITING_PRODUCT);

        $updated = $this->service->transition($r, ReturnStatus::COMPLETED, $this->adminUser);

        $this->assertEquals(ReturnStatus::COMPLETED, $updated->status);
        $this->assertEquals($this->adminUser->id, $updated->processed_by_user_id);
        $this->assertNotNull($updated->completed_at);
    }

    public function test_transition_to_cancelled_sets_cancelled_at_and_reason(): void
    {
        $r = $this->makeReturn(ReturnStatus::APPROVED);

        $updated = $this->service->transition(
            $r,
            ReturnStatus::CANCELLED,
            $this->adminUser,
            'Cliente desistiu'
        );

        $this->assertEquals(ReturnStatus::CANCELLED, $updated->status);
        $this->assertNotNull($updated->cancelled_at);
        $this->assertEquals('Cliente desistiu', $updated->cancelled_reason);
    }

    public function test_cannot_transition_from_terminal_state(): void
    {
        $r = $this->makeReturn(ReturnStatus::COMPLETED);

        $this->expectException(ValidationException::class);
        $this->service->transition($r, ReturnStatus::CANCELLED, $this->adminUser);
    }

    public function test_cannot_skip_states(): void
    {
        $r = $this->makeReturn(ReturnStatus::PENDING);

        $this->expectException(ValidationException::class);
        // pending → completed (salta) é inválido
        $this->service->transition($r, ReturnStatus::COMPLETED, $this->adminUser);
    }

    public function test_cancel_requires_note(): void
    {
        $r = $this->makeReturn(ReturnStatus::APPROVED);

        $this->expectException(ValidationException::class);
        $this->service->transition($r, ReturnStatus::CANCELLED, $this->adminUser, null);
    }

    public function test_deleted_return_cannot_transition(): void
    {
        $r = $this->makeReturn(ReturnStatus::PENDING);
        $r->update(['deleted_at' => now(), 'deleted_reason' => 'teste']);

        $this->expectException(ValidationException::class);
        $this->service->transition($r, ReturnStatus::APPROVED, $this->adminUser);
    }

    public function test_user_without_approve_cannot_approve(): void
    {
        $r = $this->makeReturn(ReturnStatus::PENDING);

        $this->expectException(ValidationException::class);
        $this->service->transition($r, ReturnStatus::APPROVED, $this->regularUser);
    }

    public function test_user_without_process_cannot_complete(): void
    {
        $r = $this->makeReturn(ReturnStatus::AWAITING_PRODUCT);

        $this->expectException(ValidationException::class);
        $this->service->transition($r, ReturnStatus::COMPLETED, $this->regularUser);
    }

    public function test_transition_dispatches_event(): void
    {
        Event::fake();
        $r = $this->makeReturn(ReturnStatus::PENDING);

        $this->service->transition($r, ReturnStatus::APPROVED, $this->adminUser);

        Event::assertDispatched(ReturnOrderStatusChanged::class, function ($e) use ($r) {
            return $e->returnOrder->id === $r->id
                && $e->fromStatus === ReturnStatus::PENDING
                && $e->toStatus === ReturnStatus::APPROVED;
        });
    }

    public function test_full_workflow_creates_4_history_rows(): void
    {
        $r = $this->makeReturn(ReturnStatus::PENDING);

        $this->service->transition($r, ReturnStatus::APPROVED, $this->adminUser);
        $this->service->transition($r->fresh(), ReturnStatus::AWAITING_PRODUCT, $this->adminUser);
        $this->service->transition($r->fresh(), ReturnStatus::PROCESSING, $this->adminUser);
        $this->service->transition($r->fresh(), ReturnStatus::COMPLETED, $this->adminUser);

        $this->assertEquals(4, $r->statusHistory()->count());
    }
}
