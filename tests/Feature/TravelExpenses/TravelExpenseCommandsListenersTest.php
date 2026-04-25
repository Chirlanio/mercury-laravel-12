<?php

namespace Tests\Feature\TravelExpenses;

use App\Console\Commands\TravelExpensesAccountabilityOverdueCommand;
use App\Console\Commands\TravelExpensesAutoCancelStaleCommand;
use App\Enums\AccountabilityStatus;
use App\Enums\Role;
use App\Enums\TravelExpenseStatus;
use App\Listeners\NotifyTravelExpenseStakeholders;
use App\Listeners\OpenHelpdeskTicketForTravelExpense;
use App\Models\HdDepartment;
use App\Models\TravelExpense;
use App\Models\TravelExpenseStatusHistory;
use App\Models\User;
use App\Notifications\TravelExpenseAccountabilityOverdueNotification;
use App\Notifications\TravelExpenseStatusChangedNotification;
use App\Services\TravelExpenseService;
use App\Services\TravelExpenseTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TravelExpenseCommandsListenersTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected int $employeeId;
    protected User $financeUser;
    protected TravelExpenseService $service;
    protected TravelExpenseTransitionService $transition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore('Z421');
        $this->employeeId = $this->createTestEmployee(['store_id' => 'Z421']);

        $this->financeUser = User::factory()->create([
            'role' => Role::FINANCE->value,
            'access_level_id' => 1,
        ]);

        $this->service = app(TravelExpenseService::class);
        $this->transition = app(TravelExpenseTransitionService::class);

        config(['queue.default' => 'sync']);
    }

    protected function autoCancelCommand(): TravelExpensesAutoCancelStaleCommand
    {
        $cmd = app(TravelExpensesAutoCancelStaleCommand::class);
        $cmd->setOutput(new \Illuminate\Console\OutputStyle(new ArrayInput([]), new BufferedOutput()));

        return $cmd;
    }

    protected function overdueCommand(): TravelExpensesAccountabilityOverdueCommand
    {
        $cmd = app(TravelExpensesAccountabilityOverdueCommand::class);
        $cmd->setOutput(new \Illuminate\Console\OutputStyle(new ArrayInput([]), new BufferedOutput()));

        return $cmd;
    }

    protected function makeApprovedExpense(array $overrides = []): TravelExpense
    {
        $payload = array_merge([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'Fortaleza',
            'destination' => 'Recife',
            'initial_date' => '2026-05-10',
            'end_date' => '2026-05-12',
            'description' => 'X',
            'pix_type_id' => 1,
            'pix_key' => '11122233344',
        ], $overrides);

        $te = $this->service->create($payload, $this->adminUser);
        $te = $this->transition->transitionExpense($te, 'submitted', $this->adminUser);
        $te = $this->transition->transitionExpense($te, 'approved', $this->adminUser);

        return $te;
    }

    // ==================================================================
    // Command: auto-cancel-stale
    // ==================================================================

    public function test_auto_cancel_cancels_old_drafts(): void
    {
        $old = $this->service->create([
            'employee_id' => $this->employeeId, 'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'velho',
        ], $this->adminUser);
        // Força created_at >30 dias atrás
        DB::table('travel_expenses')->where('id', $old->id)->update([
            'created_at' => now()->subDays(45),
        ]);

        $recent = $this->service->create([
            'employee_id' => $this->employeeId, 'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'novo',
        ], $this->adminUser);

        $cmd = $this->autoCancelCommand();
        $count = $cmd->scanTenant(30);

        $this->assertSame(1, $count);
        $this->assertSame('cancelled', $old->fresh()->status->value);
        $this->assertSame('draft', $recent->fresh()->status->value);
    }

    public function test_auto_cancel_logs_history(): void
    {
        $old = $this->service->create([
            'employee_id' => $this->employeeId, 'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
        ], $this->adminUser);
        DB::table('travel_expenses')->where('id', $old->id)->update([
            'created_at' => now()->subDays(45),
        ]);

        $cmd = $this->autoCancelCommand();
        $cmd->scanTenant(30);

        $h = TravelExpenseStatusHistory::where('travel_expense_id', $old->id)
            ->where('to_status', 'cancelled')
            ->first();

        $this->assertNotNull($h);
        $this->assertSame('draft', $h->from_status);
        $this->assertNull($h->changed_by_user_id); // operação automática
        $this->assertStringContainsString('automático', $h->note);
    }

    public function test_auto_cancel_does_not_touch_approved(): void
    {
        $approved = $this->makeApprovedExpense();
        DB::table('travel_expenses')->where('id', $approved->id)->update([
            'created_at' => now()->subDays(45),
        ]);

        $cmd = $this->autoCancelCommand();
        $count = $cmd->scanTenant(30);

        $this->assertSame(0, $count);
        $this->assertSame('approved', $approved->fresh()->status->value);
    }

    // ==================================================================
    // Command: accountability-overdue
    // ==================================================================

    public function test_overdue_notifies_stakeholders(): void
    {
        Notification::fake();

        $te = $this->makeApprovedExpense([
            'end_date' => now()->subDays(5)->toDateString(),
            'initial_date' => now()->subDays(7)->toDateString(),
        ]);

        $cmd = $this->overdueCommand();
        $sent = $cmd->scanTenant(3);

        $this->assertSame(1, $sent);
        Notification::assertSentTo(
            $this->adminUser,
            TravelExpenseAccountabilityOverdueNotification::class
        );
    }

    public function test_overdue_skips_recent_returns(): void
    {
        $te = $this->makeApprovedExpense([
            'initial_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        // Faz fake APÓS a criação para não capturar notifs de submit/approve
        Notification::fake();

        $cmd = $this->overdueCommand();
        $sent = $cmd->scanTenant(3);

        $this->assertSame(0, $sent);
        Notification::assertNothingSent();
    }

    public function test_overdue_skips_when_accountability_already_submitted(): void
    {
        Notification::fake();

        $te = $this->makeApprovedExpense([
            'initial_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
        ]);
        $te->update(['accountability_status' => AccountabilityStatus::SUBMITTED->value]);

        $cmd = $this->overdueCommand();
        $sent = $cmd->scanTenant(3);

        $this->assertSame(0, $sent);
    }

    // ==================================================================
    // Listener: NotifyTravelExpenseStakeholders
    // ==================================================================

    public function test_status_change_notifies_approvers_on_submit(): void
    {
        Notification::fake();

        $te = $this->service->create([
            'employee_id' => $this->employeeId, 'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
            'pix_type_id' => 1, 'pix_key' => '11122233344',
        ], $this->regularUser);

        $this->transition->transitionExpense($te, 'submitted', $this->regularUser);

        // adminUser e financeUser têm APPROVE_TRAVEL_EXPENSES → recebem
        Notification::assertSentTo(
            $this->adminUser,
            TravelExpenseStatusChangedNotification::class
        );
        Notification::assertSentTo(
            $this->financeUser,
            TravelExpenseStatusChangedNotification::class
        );

        // Actor (regularUser) não recebe
        Notification::assertNotSentTo($this->regularUser, TravelExpenseStatusChangedNotification::class);
    }

    public function test_status_change_notifies_creator_on_approve(): void
    {
        Notification::fake();

        $te = $this->service->create([
            'employee_id' => $this->employeeId, 'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
            'pix_type_id' => 1, 'pix_key' => '11122233344',
        ], $this->regularUser); // criador = regular
        $te = $this->transition->transitionExpense($te, 'submitted', $this->regularUser);

        $te = $this->transition->transitionExpense($te, 'approved', $this->financeUser, 'OK');

        // Criador (regularUser) recebe a notif do APPROVED
        Notification::assertSentTo(
            $this->regularUser,
            TravelExpenseStatusChangedNotification::class,
            fn ($n) => $n->toStatus === TravelExpenseStatus::APPROVED
        );
        // Actor (financeUser) não recebe a notif do APPROVED
        // (recebeu antes a do SUBMITTED, mas como APPROVER, não filtramos aqui)
        Notification::assertNotSentTo(
            $this->financeUser,
            TravelExpenseStatusChangedNotification::class,
            fn ($n) => $n->toStatus === TravelExpenseStatus::APPROVED
        );
    }

    // ==================================================================
    // Listener: OpenHelpdeskTicketForTravelExpense
    // ==================================================================

    public function test_helpdesk_ticket_skipped_when_no_financeiro_department(): void
    {
        $te = $this->service->create([
            'employee_id' => $this->employeeId, 'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
            'pix_type_id' => 1, 'pix_key' => '11122233344',
        ], $this->adminUser);
        $te = $this->transition->transitionExpense($te, 'submitted', $this->adminUser);

        // Sem depto Financeiro: rejeição não cria ticket (fail-safe)
        $te = $this->transition->transitionExpense($te, 'rejected', $this->adminUser, 'Custo elevado');

        $this->assertNull($te->fresh()->helpdesk_ticket_id);
    }

    public function test_helpdesk_ticket_created_on_rejection_when_dept_exists(): void
    {
        // Cria depto Financeiro
        $dept = HdDepartment::create([
            'name' => 'Financeiro',
            'is_active' => true,
        ]);

        $te = $this->service->create([
            'employee_id' => $this->employeeId, 'store_code' => 'Z421',
            'origin' => 'Fortaleza', 'destination' => 'Recife',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
            'pix_type_id' => 1, 'pix_key' => '11122233344',
        ], $this->adminUser);
        $te = $this->transition->transitionExpense($te, 'submitted', $this->adminUser);
        $te = $this->transition->transitionExpense($te, 'rejected', $this->adminUser, 'Sem orçamento');

        $te->refresh();
        $this->assertNotNull($te->helpdesk_ticket_id);

        $ticket = DB::table('hd_tickets')->where('id', $te->helpdesk_ticket_id)->first();
        $this->assertNotNull($ticket);
        $this->assertSame($dept->id, $ticket->department_id);
        $this->assertStringContainsString('Verba de viagem rejeitada', $ticket->title);
        $this->assertStringContainsString('Sem orçamento', $ticket->description);
    }

    public function test_helpdesk_ticket_idempotent(): void
    {
        $dept = HdDepartment::create([
            'name' => 'Financeiro',
            'is_active' => true,
        ]);

        $te = $this->service->create([
            'employee_id' => $this->employeeId, 'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
            'pix_type_id' => 1, 'pix_key' => '11122233344',
        ], $this->adminUser);
        $te = $this->transition->transitionExpense($te, 'submitted', $this->adminUser);
        $te = $this->transition->transitionExpense($te, 'rejected', $this->adminUser, 'Motivo 1');

        $firstTicket = $te->fresh()->helpdesk_ticket_id;
        $this->assertNotNull($firstTicket);

        // Dispatch manual do listener — deve ser idempotente (não cria 2º ticket)
        $listener = app(OpenHelpdeskTicketForTravelExpense::class);
        $event = new \App\Events\TravelExpenseStatusChanged(
            travelExpense: $te->fresh(),
            fromStatus: TravelExpenseStatus::SUBMITTED,
            toStatus: TravelExpenseStatus::REJECTED,
            actor: $this->adminUser,
            note: 'Tentativa duplicada',
            kind: 'expense',
        );
        $listener->handle($event);

        $te->refresh();
        $this->assertSame($firstTicket, $te->helpdesk_ticket_id);
    }
}
