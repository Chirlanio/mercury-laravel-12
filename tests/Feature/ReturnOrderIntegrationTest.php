<?php

namespace Tests\Feature;

use App\Enums\ReturnReasonCategory;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Models\ReturnOrder;
use App\Models\ReturnReason;
use App\Models\Store;
use App\Notifications\ReturnOrderStatusChangedNotification;
use App\Services\ReturnOrderTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReturnOrderIntegrationTest extends TestCase
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

    public function test_approved_notifies_creator(): void
    {
        Notification::fake();

        $r = $this->makeReturn(ReturnStatus::PENDING);
        // supportUser cria, adminUser aprova
        $r->update(['created_by_user_id' => $this->supportUser->id]);

        $this->service->transition($r, ReturnStatus::APPROVED, $this->adminUser);

        Notification::assertSentTo($this->supportUser, ReturnOrderStatusChangedNotification::class);
    }

    public function test_actor_is_excluded_from_notifications(): void
    {
        Notification::fake();

        $r = $this->makeReturn(ReturnStatus::PENDING);

        $this->service->transition($r, ReturnStatus::APPROVED, $this->adminUser);

        Notification::assertNotSentTo($this->adminUser, ReturnOrderStatusChangedNotification::class);
    }

    public function test_completed_notifies_creator(): void
    {
        Notification::fake();

        $r = $this->makeReturn(ReturnStatus::PROCESSING);
        $r->update(['created_by_user_id' => $this->supportUser->id]);

        $this->service->transition($r, ReturnStatus::COMPLETED, $this->adminUser);

        Notification::assertSentTo($this->supportUser, ReturnOrderStatusChangedNotification::class);
    }

    public function test_cancelled_notifies_creator(): void
    {
        Notification::fake();

        $r = $this->makeReturn(ReturnStatus::APPROVED);
        $r->update(['created_by_user_id' => $this->supportUser->id]);

        $this->service->transition(
            $r,
            ReturnStatus::CANCELLED,
            $this->adminUser,
            'Cliente desistiu'
        );

        Notification::assertSentTo($this->supportUser, ReturnOrderStatusChangedNotification::class);
    }

    public function test_awaiting_product_notifies_creator_and_approvers(): void
    {
        Notification::fake();

        $r = $this->makeReturn(ReturnStatus::APPROVED);
        $r->update(['created_by_user_id' => $this->supportUser->id]);

        $this->service->transition($r, ReturnStatus::AWAITING_PRODUCT, $this->adminUser);

        // Creator recebe
        Notification::assertSentTo($this->supportUser, ReturnOrderStatusChangedNotification::class);
        // Actor (admin) não
        Notification::assertNotSentTo($this->adminUser, ReturnOrderStatusChangedNotification::class);
    }
}
