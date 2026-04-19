<?php

namespace Tests\Feature;

use App\Console\Commands\ReturnOrdersStaleAlertCommand;
use App\Enums\ReturnReasonCategory;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Models\ReturnOrder;
use App\Models\ReturnReason;
use App\Models\Store;
use App\Notifications\ReturnOrderStaleAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReturnOrderCommandsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected ReturnReason $reason;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z441', 'name' => 'E-commerce']);
        $this->reason = ReturnReason::where('code', 'ARREPEND_GERAL')->firstOrFail();
    }

    protected function makeReturn(array $overrides = []): ReturnOrder
    {
        return ReturnOrder::create(array_merge([
            'invoice_number' => 'NF-'.rand(1000, 9999),
            'store_code' => $this->store->code,
            'movement_date' => now()->toDateString(),
            'customer_name' => 'Cliente',
            'sale_total' => 200,
            'type' => ReturnType::TROCA->value,
            'amount_items' => 200,
            'status' => ReturnStatus::AWAITING_PRODUCT->value,
            'reason_category' => ReturnReasonCategory::ARREPENDIMENTO->value,
            'return_reason_id' => $this->reason->id,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    protected function staleCommand(): ReturnOrdersStaleAlertCommand
    {
        $command = app(ReturnOrdersStaleAlertCommand::class);
        $command->setLaravel(app());
        $input = new ArrayInput([], $command->getDefinition());
        $command->setInput($input);
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, new BufferedOutput()));
        return $command;
    }

    public function test_stale_alert_notifies_when_stuck_in_awaiting_product(): void
    {
        Notification::fake();

        $r = $this->makeReturn(['status' => ReturnStatus::AWAITING_PRODUCT->value]);
        DB::table('return_orders')->where('id', $r->id)->update([
            'approved_at' => now()->subDays(10),
        ]);

        $sent = $this->staleCommand()->scanTenant(7);

        $this->assertGreaterThan(0, $sent);
        Notification::assertSentTo($this->adminUser, ReturnOrderStaleAlertNotification::class);
    }

    public function test_stale_alert_ignores_recent_returns(): void
    {
        Notification::fake();

        $r = $this->makeReturn(['status' => ReturnStatus::AWAITING_PRODUCT->value]);
        DB::table('return_orders')->where('id', $r->id)->update([
            'approved_at' => now()->subDay(),
        ]);

        $sent = $this->staleCommand()->scanTenant(7);

        $this->assertEquals(0, $sent);
        Notification::assertNothingSent();
    }

    public function test_stale_alert_respects_days_threshold(): void
    {
        Notification::fake();

        $r = $this->makeReturn(['status' => ReturnStatus::AWAITING_PRODUCT->value]);
        DB::table('return_orders')->where('id', $r->id)->update([
            'approved_at' => now()->subDays(5),
        ]);

        // Default 7 → não dispara
        $this->assertEquals(0, $this->staleCommand()->scanTenant(7));
        Notification::assertNothingSent();

        // Threshold 3 → dispara
        $this->assertGreaterThan(0, $this->staleCommand()->scanTenant(3));
        Notification::assertSentTo($this->adminUser, ReturnOrderStaleAlertNotification::class);
    }

    public function test_stale_alert_only_fires_for_awaiting_product(): void
    {
        Notification::fake();

        // Em pending — não deve alertar mesmo se antigo
        $r = $this->makeReturn(['status' => ReturnStatus::PENDING->value]);
        DB::table('return_orders')->where('id', $r->id)->update([
            'created_at' => now()->subDays(30),
        ]);

        $this->assertEquals(0, $this->staleCommand()->scanTenant(7));
        Notification::assertNothingSent();
    }

    public function test_stale_alert_skips_soft_deleted(): void
    {
        Notification::fake();

        $r = $this->makeReturn(['status' => ReturnStatus::AWAITING_PRODUCT->value]);
        DB::table('return_orders')->where('id', $r->id)->update([
            'approved_at' => now()->subDays(10),
            'deleted_at' => now(),
            'deleted_reason' => 'test',
        ]);

        $this->assertEquals(0, $this->staleCommand()->scanTenant(7));
        Notification::assertNothingSent();
    }
}
