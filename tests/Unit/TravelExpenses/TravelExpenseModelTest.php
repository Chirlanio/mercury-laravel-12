<?php

namespace Tests\Unit\TravelExpenses;

use App\Enums\AccountabilityStatus;
use App\Enums\TravelExpenseStatus;
use App\Models\TravelExpense;
use App\Models\TravelExpenseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class TravelExpenseModelTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected int $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->createTestStore('Z421');
        $this->employeeId = $this->createTestEmployee(['store_id' => 'Z421']);
    }

    protected function makeExpense(array $overrides = []): TravelExpense
    {
        $te = new TravelExpense();
        $te->fill(array_merge([
            'employee_id' => $this->employeeId,
            'store_code' => 'Z421',
            'origin' => 'Fortaleza',
            'destination' => 'Recife',
            'initial_date' => '2026-05-10',
            'end_date' => '2026-05-12',
            'daily_rate' => 100.00,
            'days_count' => 3,
            'value' => 300.00,
            'description' => 'Teste',
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
        $te->save();

        return $te->fresh();
    }

    public function test_status_defaults_apply_in_memory(): void
    {
        $te = $this->makeExpense();

        // Casts enum funcionam imediatamente após create graças aos $attributes defaults
        $this->assertSame(TravelExpenseStatus::DRAFT, $te->status);
        $this->assertSame(AccountabilityStatus::PENDING, $te->accountability_status);
    }

    public function test_cpf_mutator_encrypts_and_hashes(): void
    {
        $te = $this->makeExpense();
        $te->cpf = '123.456.789-00';
        $te->save();

        $te->refresh();

        // CPF é decriptado pelo accessor
        $this->assertSame('123.456.789-00', $te->cpf);

        // Hash determinístico baseado nos dígitos limpos
        $expectedHash = hash_hmac('sha256', '12345678900', config('app.key'));
        $this->assertSame($expectedHash, $te->cpf_hash);

        // Coluna `cpf_encrypted` no DB armazena valor criptografado
        $row = DB::table('travel_expenses')->where('id', $te->id)->first();
        $this->assertNotNull($row->cpf_encrypted);
        $this->assertNotEquals('123.456.789-00', $row->cpf_encrypted);
        $this->assertNotEquals('12345678900', $row->cpf_encrypted);
    }

    public function test_cpf_hash_is_consistent_for_formatted_and_unformatted(): void
    {
        $hash1 = TravelExpense::hashCpf('123.456.789-00');
        $hash2 = TravelExpense::hashCpf('12345678900');
        $this->assertSame($hash1, $hash2);
    }

    public function test_setting_cpf_to_null_clears_hash(): void
    {
        $te = $this->makeExpense();
        $te->cpf = '111.222.333-44';
        $te->save();
        $this->assertNotNull($te->fresh()->cpf_hash);

        $te->cpf = null;
        $te->save();

        $this->assertNull($te->fresh()->cpf_hash);
        $this->assertNull($te->fresh()->cpf);
    }

    public function test_masked_cpf_formats_or_returns_empty(): void
    {
        $te = $this->makeExpense();
        $this->assertSame('', $te->masked_cpf);

        $te->cpf = '12345678900';
        $te->save();
        $this->assertSame('123.456.789-00', $te->fresh()->masked_cpf);
    }

    public function test_pix_key_mutator_encrypts(): void
    {
        $te = $this->makeExpense();
        $te->pix_key = 'minha-chave-pix-secreta';
        $te->save();

        $te->refresh();
        $this->assertSame('minha-chave-pix-secreta', $te->pix_key);

        $row = DB::table('travel_expenses')->where('id', $te->id)->first();
        $this->assertNotNull($row->pix_key_encrypted);
        $this->assertNotEquals('minha-chave-pix-secreta', $row->pix_key_encrypted);
    }

    public function test_balance_computes_from_items(): void
    {
        $te = $this->makeExpense();
        $this->assertSame(300.0, $te->balance); // sem itens, balance = value

        TravelExpenseItem::create([
            'travel_expense_id' => $te->id,
            'type_expense_id' => 1,
            'expense_date' => '2026-05-10',
            'value' => 100.00,
            'description' => 'Almoço',
        ]);
        TravelExpenseItem::create([
            'travel_expense_id' => $te->id,
            'type_expense_id' => 1,
            'expense_date' => '2026-05-11',
            'value' => 250.00,
            'description' => 'Jantar caro',
        ]);

        $te->refresh();
        $this->assertSame(350.0, $te->accounted_value);
        $this->assertSame(-50.0, $te->balance); // gastou 50 a mais — saldo negativo (a reembolsar)
    }

    public function test_can_transition_to_delegates_to_enum(): void
    {
        $te = $this->makeExpense();
        $this->assertTrue($te->canTransitionTo('submitted'));
        $this->assertTrue($te->canTransitionTo(TravelExpenseStatus::CANCELLED));
        $this->assertFalse($te->canTransitionTo('approved'));
        $this->assertFalse($te->canTransitionTo('finalized'));
    }

    public function test_is_active_returns_true_for_non_terminal(): void
    {
        $te = $this->makeExpense();
        $this->assertTrue($te->isActive());
        $this->assertFalse($te->isTerminal());

        $te->status = TravelExpenseStatus::FINALIZED->value;
        $te->save();

        $this->assertFalse($te->fresh()->isActive());
        $this->assertTrue($te->fresh()->isTerminal());
    }

    public function test_for_store_scope_filters_by_store_code(): void
    {
        $this->createTestStore('Z422');
        $emp2 = $this->createTestEmployee(['store_id' => 'Z422', 'cpf' => '99999999999']);

        $this->makeExpense(['store_code' => 'Z421']);
        $this->makeExpense(['store_code' => 'Z422', 'employee_id' => $emp2]);
        $this->makeExpense(['store_code' => 'Z421']);

        $this->assertSame(2, TravelExpense::query()->forStore('Z421')->count());
        $this->assertSame(1, TravelExpense::query()->forStore('Z422')->count());
    }

    public function test_active_scope_excludes_terminal(): void
    {
        $a = $this->makeExpense();
        $b = $this->makeExpense();
        $b->update(['status' => TravelExpenseStatus::FINALIZED->value]);
        $c = $this->makeExpense();
        $c->update(['status' => TravelExpenseStatus::CANCELLED->value]);

        $active = TravelExpense::query()->active()->pluck('id')->all();
        $this->assertContains($a->id, $active);
        $this->assertNotContains($b->id, $active);
        $this->assertNotContains($c->id, $active);
    }

    public function test_accountability_overdue_scope_filters_by_end_date_and_status(): void
    {
        // Verba aprovada com retorno há 5 dias e prestação ainda em pending — deve aparecer
        $overdue = $this->makeExpense([
            'end_date' => now()->subDays(5)->toDateString(),
        ]);
        $overdue->update([
            'status' => TravelExpenseStatus::APPROVED->value,
            'accountability_status' => AccountabilityStatus::PENDING->value,
        ]);

        // Verba aprovada com retorno há 5 dias mas prestação já submitted — não deve aparecer
        $submitted = $this->makeExpense([
            'end_date' => now()->subDays(5)->toDateString(),
        ]);
        $submitted->update([
            'status' => TravelExpenseStatus::APPROVED->value,
            'accountability_status' => AccountabilityStatus::SUBMITTED->value,
        ]);

        // Verba aprovada com retorno há 1 dia (dentro do threshold) — não deve aparecer
        $recent = $this->makeExpense([
            'end_date' => now()->subDay()->toDateString(),
        ]);
        $recent->update([
            'status' => TravelExpenseStatus::APPROVED->value,
            'accountability_status' => AccountabilityStatus::PENDING->value,
        ]);

        $ids = TravelExpense::query()->accountabilityOverdue(3)->pluck('id')->all();
        $this->assertContains($overdue->id, $ids);
        $this->assertNotContains($submitted->id, $ids);
        $this->assertNotContains($recent->id, $ids);
    }

    public function test_ulid_is_auto_generated_and_unique(): void
    {
        $a = $this->makeExpense();
        $b = $this->makeExpense();

        $this->assertNotEmpty($a->ulid);
        $this->assertNotEmpty($b->ulid);
        $this->assertNotSame($a->ulid, $b->ulid);
        $this->assertSame(26, strlen($a->ulid));
    }
}
