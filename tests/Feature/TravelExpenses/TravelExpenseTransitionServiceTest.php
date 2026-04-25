<?php

namespace Tests\Feature\TravelExpenses;

use App\Enums\AccountabilityStatus;
use App\Enums\Role;
use App\Enums\TravelExpenseStatus;
use App\Events\TravelExpenseStatusChanged;
use App\Models\TravelExpense;
use App\Models\TravelExpenseItem;
use App\Models\TravelExpenseStatusHistory;
use App\Models\User;
use App\Services\TravelExpenseService;
use App\Services\TravelExpenseTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TravelExpenseTransitionServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected TravelExpenseService $service;
    protected TravelExpenseTransitionService $transition;
    protected int $employeeId;
    protected User $financeUser;

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

    protected function makeApprovedExpense(?User $actor = null): TravelExpense
    {
        $actor ??= $this->adminUser;
        $te = $this->service->create([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'Fortaleza',
            'destination' => 'Recife',
            'initial_date' => '2026-05-10',
            'end_date' => '2026-05-12',
            'description' => 'Reunião',
            'pix_type_id' => 1,
            'pix_key' => '11122233344',
        ], $actor);

        $te = $this->transition->transitionExpense($te, TravelExpenseStatus::SUBMITTED, $actor);
        $te = $this->transition->transitionExpense($te, TravelExpenseStatus::APPROVED, $actor);

        return $te;
    }

    // ==================================================================
    // State machine: solicitação
    // ==================================================================

    public function test_invalid_transition_throws(): void
    {
        $te = $this->service->create([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'Fortaleza',
            'destination' => 'Recife',
            'initial_date' => '2026-05-10',
            'end_date' => '2026-05-12',
            'description' => 'X',
        ], $this->adminUser);

        // draft → approved é inválido (precisa passar por submitted)
        $this->expectException(ValidationException::class);
        $this->transition->transitionExpense($te, 'approved', $this->adminUser);
    }

    public function test_submitted_requires_payment_info(): void
    {
        $te = $this->service->create([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
            // sem bank nem pix
        ], $this->adminUser);

        $this->expectException(ValidationException::class);
        $this->transition->transitionExpense($te, 'submitted', $this->adminUser);
    }

    public function test_rejected_requires_note(): void
    {
        $te = $this->service->create([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
            'pix_type_id' => 1, 'pix_key' => '11122233344',
        ], $this->adminUser);
        $te = $this->transition->transitionExpense($te, 'submitted', $this->adminUser);

        $this->expectException(ValidationException::class);
        $this->transition->transitionExpense($te, 'rejected', $this->adminUser); // sem note
    }

    public function test_cancelled_requires_note(): void
    {
        $te = $this->service->create([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
        ], $this->adminUser);

        $this->expectException(ValidationException::class);
        $this->transition->transitionExpense($te, 'cancelled', $this->adminUser); // sem note
    }

    public function test_finalized_requires_accountability_approved(): void
    {
        $te = $this->makeApprovedExpense();

        // accountability ainda em pending — deve bloquear
        $this->expectException(ValidationException::class);
        $this->transition->transitionExpense($te, 'finalized', $this->adminUser);
    }

    public function test_complete_happy_path(): void
    {
        $te = $this->makeApprovedExpense();

        // Adiciona um item à prestação (auto-transiciona pra in_progress)
        TravelExpenseItem::create([
            'travel_expense_id' => $te->id,
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 100,
            'description' => 'Almoço',
        ]);
        $te->update(['accountability_status' => AccountabilityStatus::IN_PROGRESS->value]);

        $te = $this->transition->transitionAccountability($te, 'submitted', $this->adminUser);
        $te = $this->transition->transitionAccountability($te, 'approved', $this->adminUser);
        $te = $this->transition->transitionExpense($te, 'finalized', $this->adminUser);

        $this->assertSame(TravelExpenseStatus::FINALIZED, $te->status);
        $this->assertSame(AccountabilityStatus::APPROVED, $te->accountability_status);
        $this->assertNotNull($te->finalized_at);
    }

    // ==================================================================
    // History trail
    // ==================================================================

    public function test_history_records_each_transition_with_kind(): void
    {
        $te = $this->makeApprovedExpense();

        $count = TravelExpenseStatusHistory::where('travel_expense_id', $te->id)->count();
        // create + submit + approve = 3 entries (kind=expense)
        $this->assertSame(3, $count);

        $expenseEntries = TravelExpenseStatusHistory::where('travel_expense_id', $te->id)
            ->where('kind', 'expense')
            ->count();
        $this->assertSame(3, $expenseEntries);
    }

    public function test_history_captures_note_and_actor(): void
    {
        $te = $this->makeApprovedExpense();
        $te = $this->transition->transitionExpense($te, 'cancelled', $this->adminUser, 'Não vai mais ser preciso');

        $last = TravelExpenseStatusHistory::where('travel_expense_id', $te->id)
            ->orderByDesc('id')->first();

        $this->assertSame('cancelled', $last->to_status);
        $this->assertSame('approved', $last->from_status);
        $this->assertSame($this->adminUser->id, $last->changed_by_user_id);
        $this->assertSame('Não vai mais ser preciso', $last->note);
    }

    // ==================================================================
    // Permissions
    // ==================================================================

    public function test_regular_user_cannot_approve(): void
    {
        $te = $this->service->create([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
            'pix_type_id' => 1, 'pix_key' => '11122233344',
        ], $this->regularUser);
        $te = $this->transition->transitionExpense($te, 'submitted', $this->regularUser);

        // regularUser não tem APPROVE_TRAVEL_EXPENSES
        $this->expectException(ValidationException::class);
        $this->transition->transitionExpense($te, 'approved', $this->regularUser);
    }

    public function test_finance_user_can_approve(): void
    {
        $te = $this->service->create([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
            'pix_type_id' => 1, 'pix_key' => '11122233344',
        ], $this->adminUser);
        $te = $this->transition->transitionExpense($te, 'submitted', $this->adminUser);

        $te = $this->transition->transitionExpense($te, 'approved', $this->financeUser, 'OK');

        $this->assertSame(TravelExpenseStatus::APPROVED, $te->status);
        $this->assertSame($this->financeUser->id, $te->approver_user_id);
    }

    // ==================================================================
    // Accountability
    // ==================================================================

    public function test_accountability_submit_requires_at_least_one_item(): void
    {
        $te = $this->makeApprovedExpense();
        // sem itens, accountability_status = pending → in_progress requer item
        $te->update(['accountability_status' => AccountabilityStatus::IN_PROGRESS->value]);

        $this->expectException(ValidationException::class);
        $this->transition->transitionAccountability($te, 'submitted', $this->adminUser);
    }

    public function test_accountability_submit_requires_expense_approved(): void
    {
        // Verba ainda em DRAFT — não pode submeter prestação
        $te = $this->service->create([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
        ], $this->adminUser);
        // Tentar pular pra in_progress mesmo sem aprovação
        $te->update(['accountability_status' => AccountabilityStatus::IN_PROGRESS->value]);
        TravelExpenseItem::create([
            'travel_expense_id' => $te->id,
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 100,
            'description' => 'X',
        ]);

        $this->expectException(ValidationException::class);
        $this->transition->transitionAccountability($te->fresh(), 'submitted', $this->adminUser);
    }

    public function test_accountability_rejected_requires_note(): void
    {
        $te = $this->makeApprovedExpense();
        TravelExpenseItem::create([
            'travel_expense_id' => $te->id,
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 100,
            'description' => 'X',
        ]);
        $te->update(['accountability_status' => AccountabilityStatus::IN_PROGRESS->value]);
        $te = $this->transition->transitionAccountability($te, 'submitted', $this->adminUser);

        $this->expectException(ValidationException::class);
        $this->transition->transitionAccountability($te, 'rejected', $this->adminUser); // sem note
    }

    // ==================================================================
    // Event dispatch
    // ==================================================================

    public function test_transition_dispatches_event(): void
    {
        Event::fake([TravelExpenseStatusChanged::class]);

        $te = $this->service->create([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'F', 'destination' => 'R',
            'initial_date' => '2026-05-10', 'end_date' => '2026-05-12',
            'description' => 'X',
            'pix_type_id' => 1, 'pix_key' => '11122233344',
        ], $this->adminUser);

        $this->transition->transitionExpense($te, 'submitted', $this->adminUser);

        Event::assertDispatched(TravelExpenseStatusChanged::class, function ($e) use ($te) {
            return $e->travelExpense->id === $te->id
                && $e->toStatus === TravelExpenseStatus::SUBMITTED
                && $e->kind === 'expense';
        });
    }

    public function test_transitioning_deleted_throws(): void
    {
        $te = $this->makeApprovedExpense();
        $te->update(['deleted_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->transition->transitionExpense($te->fresh(), 'finalized', $this->adminUser);
    }
}
