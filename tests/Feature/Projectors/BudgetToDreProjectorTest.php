<?php

namespace Tests\Feature\Projectors;

use App\Models\BudgetItem;
use App\Models\BudgetUpload;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreBudget;
use App\Models\ManagementClass;
use App\Services\DRE\BudgetToDreProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre `App\Services\DRE\BudgetToDreProjector` + observer de BudgetUpload.
 *
 * Ponte `BudgetUpload.is_active=true` → `dre_budgets`. Observer é
 * registrado em `AppServiceProvider::registerNavigationObservers()`.
 */
class BudgetToDreProjectorTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    public function test_activating_upload_projects_all_monthly_lines(): void
    {
        [$ac, $mc, $cc] = $this->scaffold();

        $upload = $this->makeUpload('ProjTest1', isActive: true);
        $this->makeItem($upload, $ac, $mc, $cc, monthlyValues: [
            1 => 100, 2 => 100, 3 => 100, 4 => 0, 5 => 0, 6 => 0,
            7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0, 12 => 150,
        ]);

        app(BudgetToDreProjector::class)->project($upload);

        // 3 meses > 0 + 1 mês (12) > 0 = 4 linhas
        $this->assertSame(4, DreBudget::where('budget_upload_id', $upload->id)->count());

        // Amounts sinalizados — account_group=4 (despesa) → negativo
        $this->assertDatabaseHas('dre_budgets', [
            'budget_upload_id' => $upload->id,
            'entry_date' => '2026-01-01',
            'amount' => -100.00,
            'budget_version' => '1.0',
        ]);
        $this->assertDatabaseHas('dre_budgets', [
            'budget_upload_id' => $upload->id,
            'entry_date' => '2026-12-01',
            'amount' => -150.00,
        ]);
    }

    public function test_activating_new_upload_supersedes_previous_active_in_same_scope(): void
    {
        [$ac, $mc, $cc] = $this->scaffold();

        // Primeira versão ativa
        $v1 = $this->makeUpload('ScopeA', isActive: true, versionLabel: '1.0');
        $this->makeItem($v1, $ac, $mc, $cc, monthlyValues: [1 => 100]);
        app(BudgetToDreProjector::class)->project($v1);
        $this->assertSame(1, DreBudget::where('budget_upload_id', $v1->id)->count());

        // Segunda versão ativa — mesmo scope/year mas version nova
        $v2 = $this->makeUpload('ScopeA', isActive: true, versionLabel: '2.0');
        $this->makeItem($v2, $ac, $mc, $cc, monthlyValues: [1 => 200, 2 => 200]);
        app(BudgetToDreProjector::class)->project($v2);

        $this->assertSame(0, DreBudget::where('budget_upload_id', $v1->id)->count());
        $this->assertSame(2, DreBudget::where('budget_upload_id', $v2->id)->count());
        $this->assertDatabaseHas('dre_budgets', [
            'budget_upload_id' => $v2->id,
            'amount' => -200.00,
            'budget_version' => '2.0',
        ]);
    }

    public function test_does_not_touch_other_scope_uploads(): void
    {
        [$ac, $mc, $cc] = $this->scaffold();

        $a = $this->makeUpload('ScopeA', isActive: true);
        $this->makeItem($a, $ac, $mc, $cc, monthlyValues: [1 => 100]);
        app(BudgetToDreProjector::class)->project($a);

        $b = $this->makeUpload('ScopeB', isActive: true);
        $this->makeItem($b, $ac, $mc, $cc, monthlyValues: [2 => 300]);
        app(BudgetToDreProjector::class)->project($b);

        $this->assertSame(1, DreBudget::where('budget_upload_id', $a->id)->count());
        $this->assertSame(1, DreBudget::where('budget_upload_id', $b->id)->count());
    }

    public function test_revenue_account_keeps_positive_sign(): void
    {
        [$ac, $mc, $cc] = $this->scaffold(accountGroup: 3);

        $upload = $this->makeUpload('RevTest', isActive: true);
        $this->makeItem($upload, $ac, $mc, $cc, monthlyValues: [1 => 1000]);

        app(BudgetToDreProjector::class)->project($upload);

        $this->assertDatabaseHas('dre_budgets', [
            'budget_upload_id' => $upload->id,
            'amount' => 1000.00,
        ]);
    }

    public function test_inactive_upload_projects_nothing(): void
    {
        [$ac, $mc, $cc] = $this->scaffold();

        $upload = $this->makeUpload('Inactive', isActive: false);
        $this->makeItem($upload, $ac, $mc, $cc, monthlyValues: [1 => 100]);

        $report = app(BudgetToDreProjector::class)->project($upload);

        $this->assertTrue($report->skippedInactive);
        $this->assertSame(0, DreBudget::where('budget_upload_id', $upload->id)->count());
    }

    public function test_unproject_removes_all_rows_of_upload(): void
    {
        [$ac, $mc, $cc] = $this->scaffold();

        $upload = $this->makeUpload('UnProj', isActive: true);
        $this->makeItem($upload, $ac, $mc, $cc, monthlyValues: [1 => 100, 2 => 100]);
        app(BudgetToDreProjector::class)->project($upload);

        $this->assertSame(2, DreBudget::where('budget_upload_id', $upload->id)->count());

        $removed = app(BudgetToDreProjector::class)->unproject($upload);
        $this->assertSame(2, $removed);
        $this->assertSame(0, DreBudget::where('budget_upload_id', $upload->id)->count());
    }

    // -----------------------------------------------------------------
    // Observer (via AppServiceProvider)
    // -----------------------------------------------------------------

    public function test_observer_projects_when_is_active_flips_to_true(): void
    {
        [$ac, $mc, $cc] = $this->scaffold();

        $upload = $this->makeUpload('ObsFlip', isActive: false);
        $this->makeItem($upload, $ac, $mc, $cc, monthlyValues: [1 => 50]);

        // Flip
        $upload->update(['is_active' => true]);

        $this->assertSame(1, DreBudget::where('budget_upload_id', $upload->id)->count());
    }

    public function test_observer_unprojects_when_is_active_flips_to_false(): void
    {
        [$ac, $mc, $cc] = $this->scaffold();

        // Criamos inativo, adicionamos itens, depois ativamos — assim o flip
        // do observer acha items para projetar.
        $upload = $this->makeUpload('ObsUnflip', isActive: false);
        $this->makeItem($upload, $ac, $mc, $cc, monthlyValues: [1 => 50]);
        $upload->update(['is_active' => true]);
        $this->assertSame(1, DreBudget::where('budget_upload_id', $upload->id)->count());

        // Agora o flip reverso deve remover.
        $upload->update(['is_active' => false]);
        $this->assertSame(0, DreBudget::where('budget_upload_id', $upload->id)->count());
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Cria conta analítica + ManagementClass + CostCenter reutilizáveis.
     *
     * @return array{0: ChartOfAccount, 1: ManagementClass, 2: CostCenter}
     */
    private function scaffold(int $accountGroup = 4): array
    {
        $ac = ChartOfAccount::factory()->analytical()->create([
            'code' => 'BUD.PROJ.'.fake()->unique()->numerify('###'),
            'account_group' => $accountGroup,
        ]);

        $cc = CostCenter::create([
            'code' => 'BUD.CC.'.fake()->unique()->numerify('###'),
            'name' => 'CC projetor test',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $area = ManagementClass::where('code', '8.1.01')->firstOrFail();

        $mc = ManagementClass::create([
            'code' => 'MC.PROJ.'.fake()->unique()->numerify('###'),
            'name' => 'MC projetor test',
            'accepts_entries' => true,
            'accounting_class_id' => $ac->id,
            'cost_center_id' => $cc->id,
            'parent_id' => $area->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        return [$ac, $mc, $cc];
    }

    private function makeUpload(
        string $scope,
        bool $isActive,
        string $versionLabel = '1.0',
        int $year = 2026,
    ): BudgetUpload {
        $area = ManagementClass::where('code', '8.1.01')->firstOrFail();

        return BudgetUpload::create([
            'year' => $year,
            'scope_label' => $scope,
            'version_label' => $versionLabel,
            'major_version' => 1,
            'minor_version' => 0,
            'upload_type' => 'novo',
            'area_department_id' => $area->id,
            'original_filename' => 't.xlsx',
            'stored_path' => "budgets/{$year}/t-".uniqid().'.xlsx',
            'file_size_bytes' => 1,
            'is_active' => $isActive,
            'total_year' => 0,
            'items_count' => 0,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);
    }

    /**
     * @param  array<int, float>  $monthlyValues  Chave 1..12, valor em reais.
     */
    private function makeItem(
        BudgetUpload $upload,
        ChartOfAccount $ac,
        ManagementClass $mc,
        CostCenter $cc,
        array $monthlyValues,
    ): BudgetItem {
        $payload = [
            'budget_upload_id' => $upload->id,
            'accounting_class_id' => $ac->id,
            'management_class_id' => $mc->id,
            'cost_center_id' => $cc->id,
        ];

        $total = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $value = (float) ($monthlyValues[$m] ?? 0);
            $payload['month_'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).'_value'] = $value;
            $total += $value;
        }
        $payload['year_total'] = $total;

        return BudgetItem::create($payload);
    }
}
