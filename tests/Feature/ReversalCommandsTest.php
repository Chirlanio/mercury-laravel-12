<?php

namespace Tests\Feature;

use App\Enums\ReversalStatus;
use App\Enums\ReversalType;
use App\Models\Reversal;
use App\Models\ReversalReason;
use App\Models\Store;
use App\Models\User;
use App\Console\Commands\ReversalsCigamPushCommand;
use App\Console\Commands\ReversalsStaleAlertCommand;
use App\Notifications\ReversalStaleAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReversalCommandsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected ReversalReason $reason;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja']);
        $this->reason = ReversalReason::where('code', 'FURO_ESTOQUE')->firstOrFail();
    }

    protected function makeReversal(array $overrides = []): Reversal
    {
        return Reversal::create(array_merge([
            'invoice_number' => 'NF-'.rand(1000, 9999),
            'store_code' => $this->store->code,
            'movement_date' => now()->toDateString(),
            'customer_name' => 'Cliente',
            'sale_total' => 500,
            'type' => ReversalType::TOTAL->value,
            'amount_original' => 500,
            'amount_reversal' => 500,
            'status' => ReversalStatus::PENDING_REVERSAL->value,
            'reversal_reason_id' => $this->reason->id,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    /**
     * Instancia o command com I/O buffered (evita dump no stdout do teste).
     */
    protected function cigamCommand(array $options = []): ReversalsCigamPushCommand
    {
        $command = app(ReversalsCigamPushCommand::class);
        $command->setLaravel(app());
        $input = new ArrayInput($options, $command->getDefinition());
        $command->setInput($input);
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, new BufferedOutput()));
        return $command;
    }

    protected function staleCommand(): ReversalsStaleAlertCommand
    {
        $command = app(ReversalsStaleAlertCommand::class);
        $command->setLaravel(app());
        $input = new ArrayInput([], $command->getDefinition());
        $command->setInput($input);
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, new BufferedOutput()));
        return $command;
    }

    // ------------------------------------------------------------------
    // reversals:cigam-push
    // ------------------------------------------------------------------

    public function test_cigam_push_only_selects_reversed_status(): void
    {
        $reversed = $this->makeReversal([
            'status' => ReversalStatus::REVERSED->value,
            'reversed_at' => now(),
        ]);
        $pending = $this->makeReversal([
            'status' => ReversalStatus::PENDING_REVERSAL->value,
        ]);

        $synced = $this->cigamCommand()->scanTenant();

        $this->assertEquals(1, $synced);
        $this->assertNotNull($reversed->fresh()->synced_to_cigam_at);
        $this->assertNull($pending->fresh()->synced_to_cigam_at);
    }

    public function test_cigam_push_is_idempotent(): void
    {
        $reversal = $this->makeReversal([
            'status' => ReversalStatus::REVERSED->value,
            'reversed_at' => now(),
        ]);

        $first = $this->cigamCommand()->scanTenant();
        $firstSync = $reversal->fresh()->synced_to_cigam_at;
        $this->assertEquals(1, $first);
        $this->assertNotNull($firstSync);

        // Segundo run não deve re-sincronizar
        $second = $this->cigamCommand()->scanTenant();
        $this->assertEquals(0, $second);
        $this->assertEquals(
            $firstSync->format('Y-m-d H:i:s'),
            $reversal->fresh()->synced_to_cigam_at->format('Y-m-d H:i:s')
        );
    }

    public function test_cigam_push_dry_run_does_not_persist(): void
    {
        $reversal = $this->makeReversal([
            'status' => ReversalStatus::REVERSED->value,
            'reversed_at' => now(),
        ]);

        $synced = $this->cigamCommand(['--dry-run' => true])->scanTenant();

        $this->assertEquals(0, $synced);
        $this->assertNull($reversal->fresh()->synced_to_cigam_at);
    }

    public function test_cigam_push_skips_soft_deleted(): void
    {
        $deleted = $this->makeReversal([
            'status' => ReversalStatus::REVERSED->value,
            'reversed_at' => now(),
            'deleted_at' => now(),
            'deleted_reason' => 'teste',
        ]);

        $synced = $this->cigamCommand()->scanTenant();

        $this->assertEquals(0, $synced);
        $this->assertNull($deleted->fresh()->synced_to_cigam_at);
    }

    // ------------------------------------------------------------------
    // reversals:stale-alert
    // ------------------------------------------------------------------

    public function test_stale_alert_notifies_approvers_of_the_store(): void
    {
        Notification::fake();

        $reversal = $this->makeReversal([
            'status' => ReversalStatus::PENDING_AUTHORIZATION->value,
        ]);
        DB::table('reversals')->where('id', $reversal->id)->update([
            'created_at' => now()->subDays(5),
        ]);

        $sent = $this->staleCommand()->scanTenant(3);

        $this->assertGreaterThan(0, $sent);
        Notification::assertSentTo($this->adminUser, ReversalStaleAlertNotification::class);
    }

    public function test_stale_alert_ignores_recent_reversals(): void
    {
        Notification::fake();

        $reversal = $this->makeReversal([
            'status' => ReversalStatus::PENDING_AUTHORIZATION->value,
        ]);
        DB::table('reversals')->where('id', $reversal->id)->update([
            'created_at' => now()->subDay(),
        ]);

        $sent = $this->staleCommand()->scanTenant(3);

        $this->assertEquals(0, $sent);
        Notification::assertNothingSent();
    }

    public function test_stale_alert_respects_days_option(): void
    {
        Notification::fake();

        $reversal = $this->makeReversal([
            'status' => ReversalStatus::PENDING_AUTHORIZATION->value,
        ]);
        DB::table('reversals')->where('id', $reversal->id)->update([
            'created_at' => now()->subDays(2),
        ]);

        // Default 3 dias — nada
        $this->assertEquals(0, $this->staleCommand()->scanTenant(3));
        Notification::assertNothingSent();

        // 1 dia — dispara
        $this->assertGreaterThan(0, $this->staleCommand()->scanTenant(1));
        Notification::assertSentTo($this->adminUser, ReversalStaleAlertNotification::class);
    }

    public function test_stale_alert_ignores_other_statuses(): void
    {
        Notification::fake();

        $reversal = $this->makeReversal([
            'status' => ReversalStatus::PENDING_REVERSAL->value,
        ]);
        DB::table('reversals')->where('id', $reversal->id)->update([
            'created_at' => now()->subDays(10),
        ]);

        $this->assertEquals(0, $this->staleCommand()->scanTenant(3));
        Notification::assertNothingSent();
    }
}
