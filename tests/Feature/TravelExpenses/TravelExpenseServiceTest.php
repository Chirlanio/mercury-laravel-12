<?php

namespace Tests\Feature\TravelExpenses;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\TravelExpenseStatus;
use App\Models\TravelExpense;
use App\Models\TravelExpenseItem;
use App\Models\User;
use App\Services\TravelExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TravelExpenseServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected TravelExpenseService $service;
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
        config(['queue.default' => 'sync']);
    }

    protected function defaultPayload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'Fortaleza',
            'destination' => 'Recife',
            'initial_date' => '2026-05-10',
            'end_date' => '2026-05-12',
            'description' => 'Reunião comercial',
        ], $overrides);
    }

    // ==================================================================
    // Cálculo de valor
    // ==================================================================

    public function test_calculate_days_inclusive(): void
    {
        // Mesmo dia → 1 dia
        $this->assertSame(1, $this->service->calculateDays('2026-05-10', '2026-05-10'));
        // Saída segunda, retorno sexta → 5 dias
        $this->assertSame(5, $this->service->calculateDays('2026-05-04', '2026-05-08'));
    }

    public function test_create_calculates_value_using_default_rate(): void
    {
        $te = $this->service->create($this->defaultPayload(), $this->adminUser);

        // 10 → 12 = 3 dias × R$ 100 = R$ 300
        $this->assertSame(3, $te->days_count);
        $this->assertSame('100.00', $te->daily_rate);
        $this->assertSame('300.00', $te->value);
    }

    public function test_create_respects_custom_daily_rate(): void
    {
        $te = $this->service->create(
            $this->defaultPayload(['daily_rate' => 250.50]),
            $this->adminUser
        );
        $this->assertSame('250.50', $te->daily_rate);
        $this->assertSame('751.50', $te->value); // 3 × 250.50
    }

    public function test_create_with_auto_submit_transitions_to_submitted(): void
    {
        $te = $this->service->create(
            $this->defaultPayload(['pix_type_id' => 1, 'pix_key' => '11122233344']),
            $this->adminUser,
            autoSubmit: true
        );

        $this->assertSame(TravelExpenseStatus::SUBMITTED, $te->status);
        $this->assertNotNull($te->submitted_at);
    }

    // ==================================================================
    // Validações
    // ==================================================================

    public function test_validate_dates_rejects_end_before_start(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create(
            $this->defaultPayload([
                'initial_date' => '2026-05-12',
                'end_date' => '2026-05-10',
            ]),
            $this->adminUser
        );
    }

    public function test_ensure_payment_info_requires_bank_or_pix(): void
    {
        $te = $this->service->create($this->defaultPayload(), $this->adminUser);

        // Sem dados bancários nem PIX, ensurePaymentInfo lança
        $this->expectException(ValidationException::class);
        $this->service->ensurePaymentInfo($te);
    }

    public function test_ensure_payment_info_passes_with_complete_bank(): void
    {
        $te = $this->service->create($this->defaultPayload([
            'bank_id' => 1,
            'bank_branch' => '001',
            'bank_account' => '12345-6',
        ]), $this->adminUser);

        // Não deve lançar
        $this->service->ensurePaymentInfo($te);
        $this->assertTrue(true);
    }

    public function test_ensure_payment_info_passes_with_pix(): void
    {
        $te = $this->service->create($this->defaultPayload([
            'pix_type_id' => 1,
            'pix_key' => '11122233344',
        ]), $this->adminUser);

        $this->service->ensurePaymentInfo($te);
        $this->assertTrue(true);
    }

    // ==================================================================
    // Update
    // ==================================================================

    public function test_update_recalculates_value_when_dates_change(): void
    {
        $te = $this->service->create($this->defaultPayload(), $this->adminUser);

        $te = $this->service->update($te, [
            'end_date' => '2026-05-15', // 6 dias agora
        ], $this->adminUser);

        $this->assertSame(6, $te->days_count);
        $this->assertSame('600.00', $te->value);
    }

    public function test_update_blocks_after_approval_for_non_managers(): void
    {
        $te = $this->service->create($this->defaultPayload(), $this->adminUser);
        $te->update(['status' => TravelExpenseStatus::APPROVED->value]);

        $this->expectException(ValidationException::class);
        $this->service->update($te, ['origin' => 'Mudei'], $this->regularUser);
    }

    public function test_update_allows_only_internal_notes_after_approval_for_managers(): void
    {
        $te = $this->service->create($this->defaultPayload(), $this->adminUser);
        $te->update(['status' => TravelExpenseStatus::APPROVED->value]);

        // adminUser tem MANAGE_TRAVEL_EXPENSES — pode editar mas só notas internas
        $te = $this->service->update($te, [
            'origin' => 'TENTATIVA',  // este será descartado
            'internal_notes' => 'Liberado financeiro',
        ], $this->adminUser);

        // origin não muda; internal_notes sim
        $this->assertSame('Fortaleza', $te->origin);
        $this->assertSame('Liberado financeiro', $te->internal_notes);
    }

    // ==================================================================
    // Delete
    // ==================================================================

    public function test_delete_blocks_for_approved_expense(): void
    {
        $te = $this->service->create($this->defaultPayload(), $this->adminUser);
        $te->update(['status' => TravelExpenseStatus::APPROVED->value]);

        $this->expectException(ValidationException::class);
        $this->service->delete($te, $this->adminUser, 'tentando excluir');
    }

    public function test_delete_blocks_when_has_items(): void
    {
        $te = $this->service->create($this->defaultPayload(), $this->adminUser);
        TravelExpenseItem::create([
            'travel_expense_id' => $te->id,
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 50,
            'description' => 'Item',
        ]);

        $this->expectException(ValidationException::class);
        $this->service->delete($te, $this->adminUser, 'tentando');
    }

    public function test_delete_succeeds_for_draft_without_items(): void
    {
        $te = $this->service->create($this->defaultPayload(), $this->adminUser);

        $this->service->delete($te, $this->adminUser, 'criada por engano');

        $te->refresh();
        $this->assertNotNull($te->deleted_at);
        $this->assertSame($this->adminUser->id, $te->deleted_by_user_id);
        $this->assertSame('criada por engano', $te->deleted_reason);
    }

    public function test_delete_blocks_already_deleted_expense(): void
    {
        $te = $this->service->create($this->defaultPayload(), $this->adminUser);
        $te->update(['deleted_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->service->delete($te->fresh(), $this->adminUser, 'duplicado');
    }

    // ==================================================================
    // Scoping
    // ==================================================================

    public function test_scoped_query_returns_all_for_manager(): void
    {
        $this->createTestStore('Z422');
        $emp2 = $this->createTestEmployee(['store_id' => 'Z422', 'cpf' => '88888888888']);

        $this->service->create($this->defaultPayload(['store_code' => 'Z421']), $this->adminUser);
        $this->service->create($this->defaultPayload(['store_code' => 'Z422', 'employee_id' => $emp2]), $this->adminUser);

        $results = $this->service->scopedQuery($this->adminUser)->get();
        $this->assertCount(2, $results);
    }

    public function test_scoped_query_filters_by_store_for_user_without_manage(): void
    {
        $this->createTestStore('Z422');
        $emp2 = $this->createTestEmployee(['store_id' => 'Z422', 'cpf' => '77777777777']);

        $this->service->create($this->defaultPayload(['store_code' => 'Z421']), $this->adminUser);
        $this->service->create($this->defaultPayload(['store_code' => 'Z422', 'employee_id' => $emp2]), $this->adminUser);

        // Regular user com store_id=Z421 só vê verbas dessa loja
        $this->regularUser->update(['store_id' => 'Z421']);

        $results = $this->service->scopedQuery($this->regularUser)->get();
        $this->assertCount(1, $results);
        $this->assertSame('Z421', $results->first()->store_code);
    }
}
