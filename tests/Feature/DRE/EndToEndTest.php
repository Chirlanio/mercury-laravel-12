<?php

namespace Tests\Feature\DRE;

use App\Enums\Permission;
use App\Models\BudgetItem;
use App\Models\BudgetUpload;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreActual;
use App\Models\DreBudget;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use App\Models\Employee;
use App\Models\ManagementClass;
use App\Models\Network;
use App\Models\OrderPayment;
use App\Models\Sale;
use App\Models\Store;
use App\Services\DRE\DreMappingResolver;
use App\Services\DRE\DreMatrixService;
use App\Services\DRE\DrePeriodClosingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Testes ponta-a-ponta do módulo DRE (playbook prompt #14).
 *
 * Cenários cobertos:
 *   1. Chart → mapping → OrderPayment → observer projeta → matriz reflete.
 *   2. Close → actual retroativo → matriz histórica imutável → reopen → diff.
 *   3. Multi-loja: scope=store isola, scope=network agrega.
 *   4. BudgetUpload ativo → dre_budgets populado → matriz mostra "Orçado".
 *
 * Isso é o fechamento do playbook — os testes unitários+feature anteriores
 * garantem cada peça; aqui verificamos que elas se conectam certo.
 */
class EndToEndTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        DreMappingResolver::resetCache();
        Cache::flush();
    }

    // -----------------------------------------------------------------
    // Cenário 1 — chart → mapping → OP → observer → matrix
    // -----------------------------------------------------------------

    public function test_order_payment_flows_end_to_end_to_matrix(): void
    {
        // Conta analítica de despesa + mapping para uma linha específica.
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'E2E.EXP.'.fake()->unique()->numerify('###'),
            'account_group' => 4,
        ]);

        $line = DreManagementLine::where('code', 'L99_UNCLASSIFIED')->firstOrFail();
        DreMapping::create([
            'chart_of_account_id' => $account->id,
            'cost_center_id' => null,
            'dre_management_line_id' => $line->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $store = Store::factory()->create();

        // OP com status=done dispara o projetor — dre_actual aparece sem
        // chamada manual.
        OrderPayment::create([
            'description' => 'E2E despesa',
            'total_value' => 420,
            'competence_date' => '2026-03-10',
            'date_payment' => '2026-03-15',
            'accounting_class_id' => $account->id,
            'store_id' => $store->id,
            'status' => OrderPayment::STATUS_DONE,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->assertDatabaseHas('dre_actuals', [
            'chart_of_account_id' => $account->id,
            'amount' => -420.00,
            'source' => DreActual::SOURCE_ORDER_PAYMENT,
        ]);

        // Matriz reflete o valor sinalizado dentro da linha mapeada.
        $matrix = app(DreMatrixService::class)->matrix([
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'scope' => 'general',
        ]);

        $lineRow = collect($matrix['lines'])->firstWhere('id', $line->id);
        $this->assertNotNull($lineRow);
        $cell = $lineRow['months']['2026-03'] ?? null;
        $this->assertNotNull($cell);
        $this->assertEqualsWithDelta(-420.00, (float) $cell['actual'], 0.01);
    }

    // -----------------------------------------------------------------
    // Cenário 2 — close + retroativo + reopen com diff
    // -----------------------------------------------------------------

    public function test_close_reopen_cycle_preserves_history_and_reports_diffs(): void
    {
        $store = Store::factory()->create();
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'E2E.CLOSE.'.fake()->unique()->numerify('###'),
            'account_group' => 4,
        ]);

        $line = DreManagementLine::where('code', 'L99_UNCLASSIFIED')->firstOrFail();

        // Lançamento inicial antes do fechamento.
        $this->makeActual($store, $account, -1000.00, '2026-03-15');

        $closing = app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        // Lançamento retroativo após o fechamento não muda o que a matriz mostra.
        $this->makeActual($store, $account, -500.00, '2026-03-20');

        $matrix = app(DreMatrixService::class)->matrix([
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'scope' => 'general',
        ]);

        $lineRow = collect($matrix['lines'])->firstWhere('id', $line->id);
        $cell = $lineRow['months']['2026-03'] ?? null;
        $this->assertEqualsWithDelta(
            -1000.00,
            (float) $cell['actual'],
            0.01,
            'Fechamento deve preservar valor original na matriz.',
        );

        // Reabrir reporta diff com o live.
        $report = app(DrePeriodClosingService::class)->reopen(
            closing: $closing,
            reopenedBy: $this->adminUser,
            reason: 'Erro contábil identificado pós-fechamento',
        );

        $this->assertTrue($report->hasDiffs());

        // Após reabrir, matriz reflete os dois lançamentos (live).
        Cache::flush();
        DreMappingResolver::resetCache();
        $liveMatrix = app(DreMatrixService::class)->matrix([
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'scope' => 'general',
        ]);
        $liveRow = collect($liveMatrix['lines'])->firstWhere('id', $line->id);
        $this->assertEqualsWithDelta(
            -1500.00,
            (float) ($liveRow['months']['2026-03']['actual'] ?? 0),
            0.01,
        );
    }

    // -----------------------------------------------------------------
    // Cenário 3 — multi-loja: scope=store isola, scope=network agrega
    // -----------------------------------------------------------------

    public function test_scope_store_isolates_and_scope_network_aggregates(): void
    {
        $network = Network::create([
            'nome' => 'E2E Rede',
            'type' => 'comercial',
            'active' => true,
        ]);

        $storeA = Store::factory()->create(['network_id' => $network->id]);
        $storeB = Store::factory()->create(['network_id' => $network->id]);
        $storeC = Store::factory()->create(['network_id' => $network->id]);

        $revenue = ChartOfAccount::factory()->revenue()->analytical()->create([
            'code' => 'E2E.REV.'.fake()->unique()->numerify('###'),
        ]);
        $line = DreManagementLine::where('code', 'L99_UNCLASSIFIED')->firstOrFail();

        $this->makeActual($storeA, $revenue, 1000.00, '2026-03-10');
        $this->makeActual($storeB, $revenue, 2000.00, '2026-03-11');
        $this->makeActual($storeC, $revenue, 3000.00, '2026-03-12');

        // scope=store só vê a loja A.
        $matrixA = app(DreMatrixService::class)->matrix([
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'scope' => 'store',
            'store_ids' => [$storeA->id],
        ]);
        $lineA = collect($matrixA['lines'])->firstWhere('id', $line->id);
        $this->assertEqualsWithDelta(1000.00, (float) ($lineA['months']['2026-03']['actual'] ?? 0), 0.01);

        // scope=network agrega as 3 lojas da rede.
        $matrixNet = app(DreMatrixService::class)->matrix([
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'scope' => 'network',
            'network_ids' => [$network->id],
        ]);
        $lineNet = collect($matrixNet['lines'])->firstWhere('id', $line->id);
        $this->assertEqualsWithDelta(6000.00, (float) ($lineNet['months']['2026-03']['actual'] ?? 0), 0.01);
    }

    // -----------------------------------------------------------------
    // Cenário 4 — BudgetUpload ativo projeta em dre_budgets
    // -----------------------------------------------------------------

    public function test_budget_upload_activation_flows_to_matrix_budget_column(): void
    {
        $ac = ChartOfAccount::factory()->analytical()->create([
            'code' => 'E2E.BUD.'.fake()->unique()->numerify('###'),
            'account_group' => 4,
        ]);

        $cc = CostCenter::create([
            'code' => 'E2E-CC-'.fake()->unique()->numerify('###'),
            'name' => 'CC E2E',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $area = ManagementClass::where('code', '8.1.01')->firstOrFail();
        $mc = ManagementClass::create([
            'code' => 'E2E-MC-'.fake()->unique()->numerify('###'),
            'name' => 'MC E2E',
            'accepts_entries' => true,
            'accounting_class_id' => $ac->id,
            'cost_center_id' => $cc->id,
            'parent_id' => $area->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        // Mapping para que o budget apareça na linha específica.
        $line = DreManagementLine::where('code', 'L99_UNCLASSIFIED')->firstOrFail();
        DreMapping::create([
            'chart_of_account_id' => $ac->id,
            'cost_center_id' => null,
            'dre_management_line_id' => $line->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        // Upload criado já ativo — observer projeta no `created` hook.
        $upload = BudgetUpload::create([
            'year' => 2026,
            'scope_label' => 'E2E',
            'version_label' => '1.0',
            'major_version' => 1,
            'minor_version' => 0,
            'upload_type' => 'novo',
            'area_department_id' => $area->id,
            'original_filename' => 'e2e.xlsx',
            'stored_path' => 'budgets/2026/e2e.xlsx',
            'file_size_bytes' => 1,
            'is_active' => false, // cria inativo
            'total_year' => 0,
            'items_count' => 0,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        BudgetItem::create([
            'budget_upload_id' => $upload->id,
            'accounting_class_id' => $ac->id,
            'management_class_id' => $mc->id,
            'cost_center_id' => $cc->id,
            'month_01_value' => 100,
            'month_02_value' => 100,
            'month_03_value' => 100,
            'month_04_value' => 0, 'month_05_value' => 0, 'month_06_value' => 0,
            'month_07_value' => 0, 'month_08_value' => 0, 'month_09_value' => 0,
            'month_10_value' => 0, 'month_11_value' => 0, 'month_12_value' => 0,
            'year_total' => 300,
        ]);

        // Agora ativa — dispara BudgetUploadDreObserver::updated → project().
        $upload->update(['is_active' => true]);

        $this->assertDatabaseCount('dre_budgets', 3);
        $this->assertDatabaseHas('dre_budgets', [
            'budget_upload_id' => $upload->id,
            'entry_date' => '2026-03-01',
            'amount' => -100.00,
            'budget_version' => '1.0',
        ]);

        // Matriz mostra o Orçado na linha mapeada.
        $matrix = app(DreMatrixService::class)->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'scope' => 'general',
        ]);
        $lineRow = collect($matrix['lines'])->firstWhere('id', $line->id);
        $this->assertNotNull($lineRow);
        $janBudget = (float) ($lineRow['months']['2026-01']['budget'] ?? 0);
        $this->assertEqualsWithDelta(-100.00, $janBudget, 0.01);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function makeActual(Store $store, ChartOfAccount $account, float $amount, string $date): DreActual
    {
        return DreActual::create([
            'entry_date' => $date,
            'chart_of_account_id' => $account->id,
            'cost_center_id' => null,
            'store_id' => $store->id,
            'amount' => $amount,
            'source' => DreActual::SOURCE_MANUAL_IMPORT,
            'source_type' => null,
            'source_id' => null,
            'reported_in_closed_period' => false,
        ]);
    }
}
