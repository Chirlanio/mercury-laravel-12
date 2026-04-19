<?php

namespace Tests\Feature;

use App\Models\AccountingClass;
use App\Models\BudgetItem;
use App\Models\BudgetUpload;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Services\BudgetConsumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BudgetConsumptionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AccountingClass $ac;

    protected ManagementClass $mc;

    protected CostCenter $cc1;

    protected CostCenter $cc2;

    protected BudgetUpload $budget;

    protected BudgetItem $item1;

    protected BudgetItem $item2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->ac = AccountingClass::where('code', '5.2.01')->firstOrFail();

        $this->cc1 = CostCenter::create([
            'code' => 'CC-CONS-1', 'name' => 'CC Admin',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->cc2 = CostCenter::create([
            'code' => 'CC-CONS-2', 'name' => 'CC TI',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->mc = ManagementClass::create([
            'code' => 'MC-CONS', 'name' => 'Gerencial Consumo',
            'accepts_entries' => true, 'accounting_class_id' => $this->ac->id,
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->budget = BudgetUpload::create([
            'year' => 2026,
            'scope_label' => 'TestConsumption',
            'version_label' => '1.0',
            'major_version' => 1,
            'minor_version' => 0,
            'upload_type' => 'novo',
            'original_filename' => 'test.xlsx',
            'stored_path' => 'budgets/2026/test.xlsx',
            'file_size_bytes' => 1000,
            'is_active' => true,
            'total_year' => 24000,
            'items_count' => 2,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        // item1: CC1, previsto = 1000/mês × 12 = 12000
        $this->item1 = BudgetItem::create([
            'budget_upload_id' => $this->budget->id,
            'accounting_class_id' => $this->ac->id,
            'management_class_id' => $this->mc->id,
            'cost_center_id' => $this->cc1->id,
            'month_01_value' => 1000, 'month_02_value' => 1000, 'month_03_value' => 1000,
            'month_04_value' => 1000, 'month_05_value' => 1000, 'month_06_value' => 1000,
            'month_07_value' => 1000, 'month_08_value' => 1000, 'month_09_value' => 1000,
            'month_10_value' => 1000, 'month_11_value' => 1000, 'month_12_value' => 1000,
            'year_total' => 12000,
        ]);

        // item2: CC2, previsto = 1000/mês × 12 = 12000
        $this->item2 = BudgetItem::create([
            'budget_upload_id' => $this->budget->id,
            'accounting_class_id' => $this->ac->id,
            'management_class_id' => $this->mc->id,
            'cost_center_id' => $this->cc2->id,
            'month_01_value' => 1000, 'month_02_value' => 1000, 'month_03_value' => 1000,
            'month_04_value' => 1000, 'month_05_value' => 1000, 'month_06_value' => 1000,
            'month_07_value' => 1000, 'month_08_value' => 1000, 'month_09_value' => 1000,
            'month_10_value' => 1000, 'month_11_value' => 1000, 'month_12_value' => 1000,
            'year_total' => 12000,
        ]);
    }

    /**
     * Insere OrderPayment direto via DB para evitar dependência de
     * factory/service do módulo OrderPayments.
     */
    protected function createOrderPayment(array $overrides = []): int
    {
        return DB::table('order_payments')->insertGetId(array_merge([
            'description' => 'OP de teste',
            'total_value' => 500,
            'date_payment' => '2026-01-15',
            'payment_type' => 'pix',
            'status' => 'waiting',
            'installments' => 1,
            'advance' => false,
            'advance_amount' => 0,
            'advance_paid' => false,
            'proof' => false,
            'payment_prepared' => false,
            'has_allocation' => false,
            'created_by_user_id' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_consumption_zero_when_no_order_payments(): void
    {
        $service = app(BudgetConsumptionService::class);
        $result = $service->getConsumption($this->budget);

        $this->assertEquals(24000, $result['totals']['forecast']);
        $this->assertEquals(0, $result['totals']['realized']);
        $this->assertEquals(24000, $result['totals']['available']);
        $this->assertEquals(0.0, $result['totals']['utilization_pct']);
    }

    public function test_consumption_aggregates_by_item(): void
    {
        // 2 OPs no item1 totalizando 5000, 0 no item2
        $this->createOrderPayment(['budget_item_id' => $this->item1->id, 'total_value' => 2000]);
        $this->createOrderPayment(['budget_item_id' => $this->item1->id, 'total_value' => 3000]);

        $service = app(BudgetConsumptionService::class);
        $result = $service->getConsumption($this->budget);

        $this->assertEquals(5000, $result['totals']['realized']);

        $byItem = collect($result['by_item']);
        $itm1 = $byItem->firstWhere('id', $this->item1->id);
        $itm2 = $byItem->firstWhere('id', $this->item2->id);

        $this->assertEquals(5000, $itm1['realized']);
        $this->assertEquals(0, $itm2['realized']);
        $this->assertEqualsWithDelta(41.67, $itm1['utilization_pct'], 0.01);
        $this->assertEquals(0, $itm2['utilization_pct']);
    }

    public function test_consumption_ignores_backlog_and_deleted_ops(): void
    {
        // Backlog — não deve contar
        $this->createOrderPayment([
            'budget_item_id' => $this->item1->id,
            'total_value' => 10000,
            'status' => 'backlog',
        ]);

        // Deletada — não deve contar
        $this->createOrderPayment([
            'budget_item_id' => $this->item1->id,
            'total_value' => 5000,
            'status' => 'waiting',
            'deleted_at' => now(),
        ]);

        // Válida — deve contar
        $this->createOrderPayment([
            'budget_item_id' => $this->item1->id,
            'total_value' => 1500,
            'status' => 'done',
        ]);

        $service = app(BudgetConsumptionService::class);
        $result = $service->getConsumption($this->budget);

        $this->assertEquals(1500, $result['totals']['realized']);
    }

    public function test_consumption_aggregates_by_cost_center(): void
    {
        $this->createOrderPayment(['budget_item_id' => $this->item1->id, 'total_value' => 6000]);
        $this->createOrderPayment(['budget_item_id' => $this->item2->id, 'total_value' => 3000]);

        $service = app(BudgetConsumptionService::class);
        $result = $service->getConsumption($this->budget);

        $byCc = collect($result['by_cost_center']);
        $this->assertCount(2, $byCc);

        $cc1Agg = $byCc->firstWhere('id', $this->cc1->id);
        $cc2Agg = $byCc->firstWhere('id', $this->cc2->id);

        $this->assertEquals(12000, $cc1Agg['forecast']);
        $this->assertEquals(6000, $cc1Agg['realized']);
        $this->assertEquals(50.0, $cc1Agg['utilization_pct']);
        $this->assertEquals('ok', $cc1Agg['status']);

        $this->assertEquals(12000, $cc2Agg['forecast']);
        $this->assertEquals(3000, $cc2Agg['realized']);
        $this->assertEquals(25.0, $cc2Agg['utilization_pct']);
    }

    public function test_utilization_status_thresholds(): void
    {
        // CC1: 50% (ok), CC2: 85% (warning)
        $this->createOrderPayment(['budget_item_id' => $this->item1->id, 'total_value' => 6000]);
        $this->createOrderPayment(['budget_item_id' => $this->item2->id, 'total_value' => 10200]);

        $service = app(BudgetConsumptionService::class);
        $result = $service->getConsumption($this->budget);

        $byCc = collect($result['by_cost_center']);
        $cc1 = $byCc->firstWhere('id', $this->cc1->id);
        $cc2 = $byCc->firstWhere('id', $this->cc2->id);

        $this->assertEquals('ok', $cc1['status']);        // 50% → ok
        $this->assertEquals('warning', $cc2['status']);   // 85% → warning
    }

    public function test_utilization_status_exceeded_when_over_100_pct(): void
    {
        $this->createOrderPayment(['budget_item_id' => $this->item1->id, 'total_value' => 15000]);

        $service = app(BudgetConsumptionService::class);
        $result = $service->getConsumption($this->budget);

        $cc1 = collect($result['by_cost_center'])->firstWhere('id', $this->cc1->id);
        $this->assertEquals('exceeded', $cc1['status']);
        $this->assertEquals(125.0, $cc1['utilization_pct']);
    }

    public function test_consumption_by_month_matches_forecast(): void
    {
        $service = app(BudgetConsumptionService::class);
        $result = $service->getConsumption($this->budget);

        $byMonth = $result['by_month'];
        $this->assertCount(12, $byMonth);

        foreach ($byMonth as $m) {
            // 2 items × 1000/mês = 2000 por mês
            $this->assertEquals(2000, $m['forecast']);
            $this->assertEquals(0, $m['realized']);
        }
    }

    public function test_dashboard_page_renders_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('budgets.dashboard', $this->budget->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Budgets/Dashboard')
            ->has('budget')
            ->has('consumption.totals')
            ->has('consumption.by_item')
            ->has('consumption.by_cost_center')
            ->has('consumption.by_month'));
    }

    public function test_dashboard_404_for_deleted_budget(): void
    {
        $this->budget->forceFill([
            'is_active' => false,
            'deleted_at' => now(),
            'deleted_by_user_id' => $this->adminUser->id,
            'deleted_reason' => 'test',
        ])->save();

        $response = $this->actingAs($this->adminUser)
            ->get(route('budgets.dashboard', $this->budget->id));

        $response->assertStatus(404);
    }

    public function test_consumption_json_endpoint(): void
    {
        $this->createOrderPayment(['budget_item_id' => $this->item1->id, 'total_value' => 1000]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('budgets.consumption', $this->budget->id));

        $response->assertStatus(200);
        $response->assertJsonPath('totals.realized', 1000);
        $response->assertJsonPath('totals.forecast', 24000);
    }

    public function test_regular_user_cannot_view_consumption(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('budgets.dashboard', $this->budget->id));

        $response->assertStatus(403);
    }

    public function test_items_for_cost_center_returns_items_from_active_budget(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('budgets.items-for-cost-center', [
                'costCenter' => $this->cc1->id,
                'year' => 2026,
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['items' => [['id', 'label', 'accounting_class', 'management_class', 'year_total', 'budget_upload']]]);
        $this->assertCount(1, $response->json('items'));
        $this->assertEquals($this->item1->id, $response->json('items.0.id'));
    }

    public function test_items_for_cost_center_skips_inactive_budgets(): void
    {
        $this->budget->update(['is_active' => false]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('budgets.items-for-cost-center', [
                'costCenter' => $this->cc1->id,
                'year' => 2026,
            ]));

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('items'));
    }

    public function test_items_for_cost_center_filters_by_year(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('budgets.items-for-cost-center', [
                'costCenter' => $this->cc1->id,
                'year' => 2025, // ano diferente — nenhum budget ativo
            ]));

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('items'));
    }
}
