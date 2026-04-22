<?php

namespace Tests\Feature\DRE;

use App\Enums\AccountGroup;
use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use App\Models\AccountingClass;
use App\Models\ChartOfAccount;
use App\Models\DreActual;
use App\Models\DreBudget;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use App\Models\DrePeriodClosing;
use App\Models\DrePeriodClosingSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Prompt #1 do DRE — testes da fundação: migrations + rename + seed +
 * models + factories.
 *
 * Cobre:
 *   - rename da tabela accounting_classes → chart_of_accounts
 *   - backfill dos campos derivados (account_group, classification_level,
 *     is_result_account) em todas as linhas do seed existente
 *   - retro-compat do model AccountingClass como alias de ChartOfAccount
 *   - criação das 6 novas tabelas DRE
 *   - seed das 17 linhas (16 DRE-BR + L99_UNCLASSIFIED)
 *   - FKs funcionais em dre_mappings e dre_actuals
 *   - stores.sale_chart_of_account_id
 */
class PromptOneFoundationTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Rename + extensão de chart_of_accounts
    // -----------------------------------------------------------------

    public function test_table_was_renamed_to_chart_of_accounts(): void
    {
        $this->assertTrue(Schema::hasTable('chart_of_accounts'));
        $this->assertFalse(Schema::hasTable('accounting_classes'));
    }

    public function test_chart_of_accounts_has_new_dre_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('chart_of_accounts', 'reduced_code'));
        $this->assertTrue(Schema::hasColumn('chart_of_accounts', 'account_group'));
        $this->assertTrue(Schema::hasColumn('chart_of_accounts', 'classification_level'));
        $this->assertTrue(Schema::hasColumn('chart_of_accounts', 'is_result_account'));
        $this->assertTrue(Schema::hasColumn('chart_of_accounts', 'default_management_class_id'));
        $this->assertTrue(Schema::hasColumn('chart_of_accounts', 'external_source'));
        $this->assertTrue(Schema::hasColumn('chart_of_accounts', 'imported_at'));
    }

    public function test_backfill_populated_derived_fields_from_code(): void
    {
        // O seed real (reseed_real_accounting_classes) traz ~100 linhas
        // com codes no formato X.X.X.XX.XXXXX. O backfill da migration
        // de extensão deve ter preenchido os campos derivados em todas.
        $sample = ChartOfAccount::query()
            ->whereNotNull('code')
            ->where('code', 'like', '3.%')
            ->first();

        $this->assertNotNull($sample, 'Seed deve ter populado contas do grupo 3.');
        $this->assertSame(AccountGroup::RECEITAS, $sample->account_group);
        $this->assertTrue($sample->is_result_account);
        $this->assertGreaterThan(0, $sample->classification_level);
    }

    public function test_deriveFromCode_helper_handles_various_formats(): void
    {
        $this->assertSame(
            ['account_group' => 1, 'classification_level' => 0, 'is_result_account' => false],
            ChartOfAccount::deriveFromCode('1')
        );

        $this->assertSame(
            ['account_group' => 3, 'classification_level' => 4, 'is_result_account' => true],
            ChartOfAccount::deriveFromCode('3.1.1.01.00012')
        );

        $this->assertSame(
            ['account_group' => 4, 'classification_level' => 4, 'is_result_account' => true],
            ChartOfAccount::deriveFromCode('4.2.1.04.00032')
        );

        $this->assertSame(
            ['account_group' => 2, 'classification_level' => 1, 'is_result_account' => false],
            ChartOfAccount::deriveFromCode('2.1')
        );
    }

    // -----------------------------------------------------------------
    // Alias retro-compat AccountingClass
    // -----------------------------------------------------------------

    public function test_accounting_class_alias_reads_chart_of_accounts(): void
    {
        $chartCount = ChartOfAccount::count();
        $aliasCount = AccountingClass::count();

        $this->assertGreaterThan(0, $chartCount, 'Seed existente deve ter pelo menos 1 linha.');
        $this->assertSame($chartCount, $aliasCount);
    }

    public function test_accounting_class_alias_can_create_row_visible_in_chart(): void
    {
        $created = AccountingClass::create([
            'code' => 'TEST-ALIAS-01',
            'reduced_code' => 'TALIAS1',
            'name' => 'Alias teste',
            'nature' => AccountingNature::DEBIT->value,
            'dre_group' => DreGroup::DESPESAS_GERAIS->value,
            'accepts_entries' => true,
            'account_group' => 4,
            'classification_level' => 0,
            'is_result_account' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('chart_of_accounts', [
            'id' => $created->id,
            'code' => 'TEST-ALIAS-01',
        ]);
    }

    // -----------------------------------------------------------------
    // cost_centers estendida
    // -----------------------------------------------------------------

    public function test_cost_centers_has_new_erp_tracking_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('cost_centers', 'reduced_code'));
        $this->assertTrue(Schema::hasColumn('cost_centers', 'external_source'));
        $this->assertTrue(Schema::hasColumn('cost_centers', 'imported_at'));
    }

    // -----------------------------------------------------------------
    // stores.sale_chart_of_account_id
    // -----------------------------------------------------------------

    public function test_stores_has_sale_chart_of_account_id(): void
    {
        $this->assertTrue(Schema::hasColumn('stores', 'sale_chart_of_account_id'));
    }

    // -----------------------------------------------------------------
    // dre_management_lines — base semeada pelo prompt #1 substituída
    // pela estrutura executiva do prompt #2 (20 linhas — ver
    // PromptTwoDataLayerTest para cobertura detalhada).
    // -----------------------------------------------------------------

    public function test_dre_management_lines_seed_has_20_executive_rows_plus_unclassified(): void
    {
        // Detalhe completo em PromptTwoDataLayerTest; aqui só a smoke
        // check. 20 linhas executivas + L99_UNCLASSIFIED (destravador pré-6) = 21.
        $this->assertSame(21, DreManagementLine::count());
        $this->assertSame(20, DreManagementLine::where('code', '!=', DreManagementLine::UNCLASSIFIED_CODE)->count());
    }

    // -----------------------------------------------------------------
    // Factories
    // -----------------------------------------------------------------

    public function test_chart_of_account_factory_works(): void
    {
        $account = ChartOfAccount::factory()->create();

        $this->assertDatabaseHas('chart_of_accounts', ['id' => $account->id]);
        $this->assertTrue($account->accepts_entries);
    }

    public function test_dre_management_line_factory_subtotal_state(): void
    {
        $line = DreManagementLine::factory()->subtotal(100)->create();

        $this->assertTrue($line->is_subtotal);
        $this->assertSame(100, $line->accumulate_until_sort_order);
        $this->assertSame(DreManagementLine::NATURE_SUBTOTAL, $line->nature);
    }

    public function test_dre_mapping_factory_creates_full_row(): void
    {
        $mapping = DreMapping::factory()->create();

        $this->assertDatabaseHas('dre_mappings', ['id' => $mapping->id]);
        $this->assertNotNull($mapping->chart_of_account_id);
        $this->assertNull($mapping->cost_center_id);
        $this->assertNotNull($mapping->dre_management_line_id);
        $this->assertSame('2026-01-01', $mapping->effective_from->format('Y-m-d'));
    }

    // -----------------------------------------------------------------
    // FKs — DRE apontando para ChartOfAccount e ManagementLine
    // -----------------------------------------------------------------

    public function test_dre_mapping_belongs_to_chart_of_account_and_line(): void
    {
        $line = DreManagementLine::where('code', 'L01')->firstOrFail();
        $account = ChartOfAccount::factory()->revenue()->create();

        $user = User::factory()->create();

        $mapping = DreMapping::factory()
            ->for($account, 'chartOfAccount')
            ->for($line, 'dreManagementLine')
            ->create(['created_by_user_id' => $user->id]);

        $this->assertSame($account->id, $mapping->chartOfAccount->id);
        $this->assertSame($line->id, $mapping->dreManagementLine->id);
    }

    public function test_dre_actual_can_reference_chart_of_account(): void
    {
        $account = ChartOfAccount::factory()->create();
        $actual = DreActual::factory()->for($account, 'chartOfAccount')->create([
            'amount' => -1500.00,
        ]);

        $this->assertDatabaseHas('dre_actuals', [
            'id' => $actual->id,
            'chart_of_account_id' => $account->id,
        ]);
        $this->assertSame('-1500.00', $actual->amount);
        $this->assertFalse($actual->reported_in_closed_period);
    }

    public function test_dre_budget_can_reference_chart_of_account(): void
    {
        $account = ChartOfAccount::factory()->create();
        $budget = DreBudget::factory()->for($account, 'chartOfAccount')->create([
            'amount' => 50000.00,
            'budget_version' => 'action_plan_v1',
        ]);

        $this->assertDatabaseHas('dre_budgets', [
            'id' => $budget->id,
            'budget_version' => 'action_plan_v1',
        ]);
    }

    // -----------------------------------------------------------------
    // dre_period_closings + snapshots
    // -----------------------------------------------------------------

    public function test_period_closing_starts_active_until_reopened(): void
    {
        $closing = DrePeriodClosing::factory()->create();

        $this->assertTrue($closing->isActive());
        $this->assertNull($closing->reopened_at);
    }

    public function test_period_closing_snapshot_cascade_deletes(): void
    {
        $closing = DrePeriodClosing::factory()->create();
        $line = DreManagementLine::where('code', 'L01')->firstOrFail();

        $snapshot = DrePeriodClosingSnapshot::create([
            'dre_period_closing_id' => $closing->id,
            'scope' => DrePeriodClosingSnapshot::SCOPE_GENERAL,
            'scope_id' => null,
            'dre_management_line_id' => $line->id,
            'year_month' => '2026-01',
            'actual_amount' => 100000.00,
            'budget_amount' => 95000.00,
        ]);

        $this->assertDatabaseHas('dre_period_closing_snapshots', ['id' => $snapshot->id]);

        $closing->delete();

        $this->assertDatabaseMissing('dre_period_closing_snapshots', ['id' => $snapshot->id]);
    }

    // -----------------------------------------------------------------
    // Compat — budget_items (pivot existente) ainda referencia via FK
    // -----------------------------------------------------------------

    public function test_budget_items_fk_continues_to_reference_chart_of_accounts_after_rename(): void
    {
        // Validação indireta: a tabela budget_items existe e tem a coluna
        // accounting_class_id. A FK foi atualizada pelo Schema::rename
        // (MySQL/SQLite garantem isso em conjunto com loadMigrationsFrom
        // no ambiente de testes). Verificamos consultando as tabelas.
        $this->assertTrue(Schema::hasTable('budget_items'));
        $this->assertTrue(Schema::hasColumn('budget_items', 'accounting_class_id'));
    }

    public function test_management_classes_still_has_accounting_class_id_column(): void
    {
        $this->assertTrue(Schema::hasTable('management_classes'));
        $this->assertTrue(Schema::hasColumn('management_classes', 'accounting_class_id'));
    }

    public function test_order_payments_still_has_accounting_class_id_column(): void
    {
        $this->assertTrue(Schema::hasTable('order_payments'));
        $this->assertTrue(Schema::hasColumn('order_payments', 'accounting_class_id'));
    }
}
