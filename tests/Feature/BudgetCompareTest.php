<?php

namespace Tests\Feature;

use App\Models\AccountingClass;
use App\Models\BudgetItem;
use App\Models\BudgetUpload;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Services\BudgetDiffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre BudgetDiffService + endpoint /budgets/compare (Melhoria 9).
 *
 * Testa:
 *   - Diff identifica added, removed, changed por chave (AC, MC, CC, store)
 *   - Totais e deltas mensais batem
 *   - Rejeita versões de escopos ou anos diferentes
 *   - Endpoint renderiza payload com shape esperado
 */
class BudgetCompareTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AccountingClass $ac1;

    protected AccountingClass $ac2;

    protected CostCenter $cc;

    protected ManagementClass $mc;

    protected ManagementClass $areaDept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        app(\App\Services\CentralRoleResolver::class)->clearCache();

        $this->ac1 = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail();
        $this->ac2 = AccountingClass::where('code', '4.2.1.04.00083')->firstOrFail();
        $this->cc = CostCenter::create([
            'code' => 'CC-DIFF', 'name' => 'CC Diff',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->areaDept = ManagementClass::where('code', '8.1.01')->firstOrFail();
        $this->mc = ManagementClass::create([
            'code' => 'MC-DIFF', 'name' => 'MC Diff',
            'accepts_entries' => true,
            'parent_id' => $this->areaDept->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    protected function makeBudget(string $scope, int $year, int $minor): BudgetUpload
    {
        return BudgetUpload::create([
            'year' => $year, 'scope_label' => $scope,
            'version_label' => "1.{$minor}",
            'major_version' => 1, 'minor_version' => $minor,
            'upload_type' => $minor === 0 ? 'novo' : 'ajuste',
            'area_department_id' => $this->areaDept->id,
            'original_filename' => "t-{$scope}-{$minor}.xlsx",
            'stored_path' => "budgets/{$year}/t-{$scope}-{$minor}.xlsx",
            'file_size_bytes' => 1, 'is_active' => false,
            'total_year' => 0, 'items_count' => 0,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);
    }

    protected function addItem(BudgetUpload $b, int $acId, int $ccId, array $monthly, string $supplier = ''): BudgetItem
    {
        $data = array_merge([
            'budget_upload_id' => $b->id,
            'accounting_class_id' => $acId,
            'management_class_id' => $this->mc->id,
            'cost_center_id' => $ccId,
            'supplier' => $supplier,
            'year_total' => array_sum($monthly),
        ], $this->monthArray($monthly));
        $item = BudgetItem::create($data);
        $b->total_year = $b->items()->sum('year_total');
        $b->items_count = $b->items()->count();
        $b->save();

        return $item;
    }

    protected function monthArray(array $monthly): array
    {
        $out = [];
        foreach (range(1, 12) as $m) {
            $out['month_'.str_pad($m, 2, '0', STR_PAD_LEFT).'_value'] = $monthly[$m - 1] ?? 0;
        }

        return $out;
    }

    public function test_diff_identifies_added_removed_and_changed(): void
    {
        $v1 = $this->makeBudget('Scope', 2026, 0);
        $v2 = $this->makeBudget('Scope', 2026, 1);

        // Unchanged em ambos (AC1/CC)
        $this->addItem($v1, $this->ac1->id, $this->cc->id, array_fill(0, 12, 100));
        $this->addItem($v2, $this->ac1->id, $this->cc->id, array_fill(0, 12, 100));

        // Changed (AC2/CC — valores mudam)
        $this->addItem($v1, $this->ac2->id, $this->cc->id, array_fill(0, 12, 50));
        $this->addItem($v2, $this->ac2->id, $this->cc->id, array_fill(0, 12, 80)); // +30/mês

        // Removed — só em v1 (criamos um CC extra pra outra chave)
        $ccExtra = CostCenter::create([
            'code' => 'CC-EXTRA', 'name' => 'Extra',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->addItem($v1, $this->ac1->id, $ccExtra->id, array_fill(0, 12, 200));

        // Added — só em v2
        $ccNovo = CostCenter::create([
            'code' => 'CC-NOVO', 'name' => 'Novo',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->addItem($v2, $this->ac1->id, $ccNovo->id, array_fill(0, 12, 120));

        $diff = app(BudgetDiffService::class)->diff($v1->fresh(), $v2->fresh());

        $this->assertCount(1, $diff['added']);
        $this->assertCount(1, $diff['removed']);
        $this->assertCount(1, $diff['changed']);
        $this->assertEquals(1, $diff['unchanged_count']);

        // Totais: v1 = 1200+600+2400 = 4200; v2 = 1200+960+1440 = 3600
        $this->assertEquals(4200, $diff['totals']['v1']);
        $this->assertEquals(3600, $diff['totals']['v2']);
        $this->assertEquals(-600, $diff['totals']['delta']);

        // Changed: AC2/CC com delta +360 anual (+30/mês)
        $changedItem = $diff['changed'][0];
        $this->assertEquals(360, $changedItem['year_total_delta']);
        $this->assertEquals(12, count($changedItem['months']));
        $this->assertEquals(30, $changedItem['months'][0]['delta']);
    }

    public function test_diff_rejects_different_scopes(): void
    {
        $v1 = $this->makeBudget('ScopeA', 2026, 0);
        $v2 = $this->makeBudget('ScopeB', 2026, 0);

        $this->expectException(ValidationException::class);
        app(BudgetDiffService::class)->diff($v1, $v2);
    }

    public function test_diff_rejects_different_years(): void
    {
        $v1 = $this->makeBudget('Scope', 2026, 0);
        $v2 = $this->makeBudget('Scope', 2027, 0);

        $this->expectException(ValidationException::class);
        app(BudgetDiffService::class)->diff($v1, $v2);
    }

    public function test_diff_rejects_same_budget(): void
    {
        $v1 = $this->makeBudget('Scope', 2026, 0);

        $this->expectException(ValidationException::class);
        app(BudgetDiffService::class)->diff($v1, $v1);
    }

    public function test_by_month_sums_across_all_items(): void
    {
        $v1 = $this->makeBudget('Scope', 2026, 0);
        $v2 = $this->makeBudget('Scope', 2026, 1);

        // v1: item1 com 100 em jan; v2: item1 com 200 em jan — delta mensal +100
        $m1 = array_fill(0, 12, 0); $m1[0] = 100;
        $m2 = array_fill(0, 12, 0); $m2[0] = 200;
        $this->addItem($v1, $this->ac1->id, $this->cc->id, $m1);
        $this->addItem($v2, $this->ac1->id, $this->cc->id, $m2);

        $diff = app(BudgetDiffService::class)->diff($v1->fresh(), $v2->fresh());

        $this->assertEquals(100, $diff['by_month'][0]['delta']);
        $this->assertEquals(0, $diff['by_month'][1]['delta']);
    }

    public function test_endpoint_renders_compare_page_with_diff(): void
    {
        $v1 = $this->makeBudget('Scope', 2026, 0);
        $v2 = $this->makeBudget('Scope', 2026, 1);
        $this->addItem($v1, $this->ac1->id, $this->cc->id, array_fill(0, 12, 100));
        $this->addItem($v2, $this->ac1->id, $this->cc->id, array_fill(0, 12, 150));

        $response = $this->actingAs($this->adminUser)
            ->get(route('budgets.compare', ['v1' => $v1->id, 'v2' => $v2->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Budgets/Compare')
            ->has('diff')
            ->has('diff.v1')
            ->has('diff.v2')
            ->has('diff.changed')
            ->has('diff.totals'));
    }

    public function test_endpoint_renders_error_when_budgets_from_different_scopes(): void
    {
        $v1 = $this->makeBudget('ScopeX', 2026, 0);
        $v2 = $this->makeBudget('ScopeY', 2026, 0);

        $response = $this->actingAs($this->adminUser)
            ->get(route('budgets.compare', ['v1' => $v1->id, 'v2' => $v2->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Budgets/Compare')
            ->where('diff', null)
            ->where('error', fn ($msg) => str_contains($msg, 'escopo')));
    }

    public function test_endpoint_without_params_renders_error(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('budgets.compare'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Budgets/Compare')
            ->where('diff', null));
    }
}
