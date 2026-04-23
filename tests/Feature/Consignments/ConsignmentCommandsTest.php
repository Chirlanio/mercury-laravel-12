<?php

namespace Tests\Feature\Consignments;

use App\Console\Commands\ConsignmentsCigamMatchCommand;
use App\Console\Commands\ConsignmentsMarkOverdueCommand;
use App\Console\Commands\ConsignmentsOverdueAlertCommand;
use App\Console\Commands\ConsignmentsRemindUpcomingCommand;
use App\Enums\ConsignmentStatus;
use App\Events\ConsignmentStatusChanged;
use App\Listeners\NotifyConsignmentStakeholders;
use App\Models\Consignment;
use App\Models\ConsignmentReturn;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\Store;
use App\Models\User;
use App\Notifications\ConsignmentReminderNotification;
use App\Notifications\ConsignmentStatusChangedNotification;
use App\Services\ConsignmentTransitionService;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobertura Fase 4: Event + Listener + 4 commands agendados.
 */
class ConsignmentCommandsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        app(\App\Services\CentralRoleResolver::class)->clearCache();

        MovementType::firstOrCreate(['code' => 21], ['description' => 'Retorno']);

        $this->store = Store::factory()->create(['code' => 'Z421']);

        config(['queue.default' => 'sync']);
    }

    /**
     * Injeta um OutputStyle fake nos commands — sem isso, chamadas a
     * $this->line/info/warn explodem com "writeln on null" porque o
     * command foi instanciado via container e não via Artisan facade.
     */
    protected function withOutput(\Illuminate\Console\Command $cmd): \Illuminate\Console\Command
    {
        $cmd->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        return $cmd;
    }

    // ------------------------------------------------------------------
    // Event + Listener
    // ------------------------------------------------------------------

    public function test_transition_dispatches_status_changed_event(): void
    {
        Event::fake();

        $c = Consignment::factory()->draft()->forStore($this->store)->create();
        app(ConsignmentTransitionService::class)
            ->issue($c, $this->adminUser, 'Emitido');

        Event::assertDispatched(ConsignmentStatusChanged::class, function ($event) use ($c) {
            return $event->consignment->id === $c->id
                && $event->fromStatus === ConsignmentStatus::DRAFT
                && $event->toStatus === ConsignmentStatus::PENDING
                && $event->actor?->id === $this->adminUser->id;
        });
    }

    public function test_listener_notifies_creator_on_transition(): void
    {
        Notification::fake();

        $c = Consignment::factory()
            ->pending()
            ->forStore($this->store)
            ->create(['created_by_user_id' => $this->regularUser->id]);

        $event = new ConsignmentStatusChanged(
            consignment: $c,
            fromStatus: ConsignmentStatus::PENDING,
            toStatus: ConsignmentStatus::COMPLETED,
            actor: $this->adminUser, // actor != criador
            note: 'Tudo ok',
        );

        app(NotifyConsignmentStakeholders::class)->handle($event);

        // regular (criador) deve receber; admin (actor) deve ser excluído
        Notification::assertSentTo($this->regularUser, ConsignmentStatusChangedNotification::class);
        Notification::assertNotSentTo($this->adminUser, ConsignmentStatusChangedNotification::class);
    }

    public function test_listener_skips_draft_transition(): void
    {
        Notification::fake();

        $c = Consignment::factory()
            ->draft()
            ->forStore($this->store)
            ->create(['created_by_user_id' => $this->regularUser->id]);

        // Transição hipotética para DRAFT — listener ignora
        $event = new ConsignmentStatusChanged(
            consignment: $c,
            fromStatus: ConsignmentStatus::PENDING,
            toStatus: ConsignmentStatus::DRAFT,
            actor: $this->adminUser,
        );

        app(NotifyConsignmentStakeholders::class)->handle($event);

        Notification::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Command consignments:mark-overdue
    // ------------------------------------------------------------------

    public function test_mark_overdue_transitions_past_due_consignments(): void
    {
        Consignment::factory()
            ->forStore($this->store)
            ->create([
                'status' => ConsignmentStatus::PENDING->value,
                'expected_return_date' => now()->subDays(2)->format('Y-m-d'),
                'issued_at' => now()->subDays(9),
            ]);

        Consignment::factory()
            ->forStore($this->store)
            ->create([
                'status' => ConsignmentStatus::PARTIALLY_RETURNED->value,
                'expected_return_date' => now()->subDays(1)->format('Y-m-d'),
                'issued_at' => now()->subDays(8),
            ]);

        // Uma ainda no prazo — não deve ser marcada
        Consignment::factory()
            ->forStore($this->store)
            ->create([
                'status' => ConsignmentStatus::PENDING->value,
                'expected_return_date' => now()->addDays(3)->format('Y-m-d'),
                'issued_at' => now()->subDays(4),
            ]);

        $cmd = $this->withOutput(app(ConsignmentsMarkOverdueCommand::class));
        $marked = $cmd->scanTenant(app(ConsignmentTransitionService::class));

        $this->assertEquals(2, $marked);
        $this->assertEquals(
            2,
            Consignment::where('status', ConsignmentStatus::OVERDUE->value)->count()
        );
    }

    public function test_mark_overdue_is_idempotent(): void
    {
        Consignment::factory()
            ->forStore($this->store)
            ->create([
                'status' => ConsignmentStatus::OVERDUE->value,
                'expected_return_date' => now()->subDays(2)->format('Y-m-d'),
            ]);

        $cmd = $this->withOutput(app(ConsignmentsMarkOverdueCommand::class));
        $marked = $cmd->scanTenant(app(ConsignmentTransitionService::class));

        $this->assertEquals(0, $marked, 'Já-overdue não deve reprocessar');
    }

    // ------------------------------------------------------------------
    // Command consignments:remind-upcoming
    // ------------------------------------------------------------------

    public function test_remind_upcoming_notifies_creator(): void
    {
        Notification::fake();

        $c = Consignment::factory()
            ->forStore($this->store)
            ->create([
                'status' => ConsignmentStatus::PENDING->value,
                'expected_return_date' => now()->addDay()->format('Y-m-d'),
                'created_by_user_id' => $this->regularUser->id,
            ]);

        $cmd = $this->withOutput(app(ConsignmentsRemindUpcomingCommand::class));
        $sent = $cmd->scanTenant(days: 2);

        $this->assertGreaterThan(0, $sent);
        Notification::assertSentTo($this->regularUser, ConsignmentReminderNotification::class);
    }

    public function test_remind_upcoming_ignores_beyond_window(): void
    {
        Notification::fake();

        // Prazo a 10 dias — fora da janela de 2 dias
        Consignment::factory()
            ->forStore($this->store)
            ->create([
                'status' => ConsignmentStatus::PENDING->value,
                'expected_return_date' => now()->addDays(10)->format('Y-m-d'),
                'created_by_user_id' => $this->regularUser->id,
            ]);

        $cmd = $this->withOutput(app(ConsignmentsRemindUpcomingCommand::class));
        $cmd->scanTenant(days: 2);

        Notification::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Command consignments:overdue-alert
    // ------------------------------------------------------------------

    public function test_overdue_alert_notifies_managers(): void
    {
        Notification::fake();

        Consignment::factory()
            ->forStore($this->store)
            ->create([
                'status' => ConsignmentStatus::OVERDUE->value,
                'expected_return_date' => now()->subDays(10)->format('Y-m-d'),
                'created_by_user_id' => $this->regularUser->id,
            ]);

        $cmd = $this->withOutput(app(ConsignmentsOverdueAlertCommand::class));
        $sent = $cmd->scanTenant(days: 7);

        $this->assertGreaterThan(0, $sent);
        // Admin tem MANAGE_CONSIGNMENTS — deve receber
        Notification::assertSentTo($this->adminUser, ConsignmentReminderNotification::class);
        // RegularUser não tem MANAGE — não recebe este alerta (é o criador,
        // mas overdue-alert é específico pra supervisão)
        Notification::assertNotSentTo($this->regularUser, ConsignmentReminderNotification::class);
    }

    public function test_overdue_alert_skips_recent_overdue(): void
    {
        Notification::fake();

        // Overdue com 3 dias — menor que o threshold de 7
        Consignment::factory()
            ->forStore($this->store)
            ->create([
                'status' => ConsignmentStatus::OVERDUE->value,
                'expected_return_date' => now()->subDays(3)->format('Y-m-d'),
            ]);

        $cmd = $this->withOutput(app(ConsignmentsOverdueAlertCommand::class));
        $cmd->scanTenant(days: 7);

        Notification::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Command consignments:cigam-match
    // ------------------------------------------------------------------

    public function test_cigam_match_reconciles_return_with_movement(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        $return = ConsignmentReturn::create([
            'consignment_id' => $c->id,
            'return_invoice_number' => '88001',
            'return_date' => '2026-04-23',
            'return_store_code' => 'Z421',
            'returned_quantity' => 0,
            'returned_value' => 0,
            'registered_by_user_id' => $this->adminUser->id,
        ]);

        // Movement correspondente aparece no CIGAM sync
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

        $cmd = $this->withOutput(app(ConsignmentsCigamMatchCommand::class));
        $matched = $cmd->scanTenant(dry: false);

        $this->assertEquals(1, $matched);

        $return->refresh();
        $this->assertNotNull($return->movement_id);
        $this->assertNotNull($return->reconciled_at);
    }

    public function test_cigam_match_is_idempotent(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        $movement = Movement::create([
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

        // Return já vinculado
        ConsignmentReturn::create([
            'consignment_id' => $c->id,
            'return_invoice_number' => '88001',
            'return_date' => '2026-04-23',
            'return_store_code' => 'Z421',
            'returned_quantity' => 0,
            'returned_value' => 0,
            'movement_id' => $movement->id,
            'reconciled_at' => now()->subMinute(),
            'registered_by_user_id' => $this->adminUser->id,
        ]);

        $cmd = $this->withOutput(app(ConsignmentsCigamMatchCommand::class));
        $matched = $cmd->scanTenant(dry: false);

        $this->assertEquals(0, $matched, 'Returns já reconciliados devem ser ignorados');
    }
}
