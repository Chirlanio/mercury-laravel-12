<?php

namespace Tests\Feature;

use App\Console\Commands\OrderPaymentsBackfillBudgetLinksCommand;
use App\Models\AccountingClass;
use App\Models\BudgetItem;
use App\Models\BudgetUpload;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\OrderPayment;
use App\Services\OrderPaymentBudgetResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre o comando `order-payments:backfill-budget-links` (C3b do roadmap).
 *
 * O comando original itera tenants via stancl/tenancy, mas nos testes
 * usamos SQLite in-memory sem tenants registrados. Testamos a lógica
 * core (scanTenant) diretamente instanciando o comando.
 */
class OrderPaymentsBackfillBudgetLinksTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected BudgetItem $budgetItem;

    protected AccountingClass $ac;

    protected CostCenter $cc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->ac = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail();
        $this->cc = CostCenter::create([
            'code' => 'CC-BF', 'name' => 'CC Backfill',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);
        $areaDept = ManagementClass::where('code', '8.1.01')->firstOrFail();
        $mc = ManagementClass::create([
            'code' => 'MC-BF', 'name' => 'MC Backfill',
            'accepts_entries' => true,
            'parent_id' => $areaDept->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
        $budget = BudgetUpload::create([
            'year' => 2026, 'scope_label' => 'BackfillTest', 'version_label' => '1.0',
            'major_version' => 1, 'minor_version' => 0, 'upload_type' => 'novo',
            'area_department_id' => $areaDept->id,
            'original_filename' => 't.xlsx', 'stored_path' => 'budgets/t.xlsx',
            'file_size_bytes' => 1, 'is_active' => true,
            'total_year' => 12000, 'items_count' => 1,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);
        $this->budgetItem = BudgetItem::create([
            'budget_upload_id' => $budget->id,
            'accounting_class_id' => $this->ac->id,
            'management_class_id' => $mc->id,
            'cost_center_id' => $this->cc->id,
            'month_01_value' => 1000, 'month_02_value' => 1000, 'month_03_value' => 1000,
            'month_04_value' => 1000, 'month_05_value' => 1000, 'month_06_value' => 1000,
            'month_07_value' => 1000, 'month_08_value' => 1000, 'month_09_value' => 1000,
            'month_10_value' => 1000, 'month_11_value' => 1000, 'month_12_value' => 1000,
            'year_total' => 12000,
        ]);
    }

    protected function runScan(bool $apply): array
    {
        $cmd = app(OrderPaymentsBackfillBudgetLinksCommand::class);
        $cmd->setLaravel(app());
        $cmd->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
        ));

        $reflection = new \ReflectionMethod($cmd, 'scanTenant');
        $reflection->setAccessible(true);

        return $reflection->invoke($cmd, app(OrderPaymentBudgetResolver::class), $apply);
    }

    public function test_links_ops_that_have_cc_ac_and_matching_budget(): void
    {
        // OP com CC+AC+data — deve ser vinculada ao budget_item
        $op = OrderPayment::create([
            'description' => 'OP antiga',
            'total_value' => 500,
            'date_payment' => '2026-04-15',
            'cost_center_id' => $this->cc->id,
            'accounting_class_id' => $this->ac->id,
            'status' => OrderPayment::STATUS_DONE,
            'created_by_user_id' => $this->adminUser->id,
        ]);
        $op->forceFill(['budget_item_id' => null])->save();

        [$linked, $missing] = $this->runScan(apply: true);

        $this->assertEquals(1, $linked);
        $this->assertEquals(0, $missing);

        $op->refresh();
        $this->assertEquals($this->budgetItem->id, $op->budget_item_id);
    }

    public function test_dry_run_does_not_persist(): void
    {
        $op = OrderPayment::create([
            'description' => 'OP dry-run',
            'total_value' => 500,
            'date_payment' => '2026-04-15',
            'cost_center_id' => $this->cc->id,
            'accounting_class_id' => $this->ac->id,
            'status' => OrderPayment::STATUS_DONE,
            'created_by_user_id' => $this->adminUser->id,
        ]);
        $op->forceFill(['budget_item_id' => null])->save();

        [$linked, $missing] = $this->runScan(apply: false);

        $this->assertEquals(1, $linked);

        $op->refresh();
        $this->assertNull($op->budget_item_id);
    }

    public function test_counts_ops_missing_cc_or_ac_as_manual(): void
    {
        // OP sem CC — manual classification
        OrderPayment::create([
            'description' => 'Sem CC',
            'total_value' => 200,
            'date_payment' => '2026-04-15',
            'accounting_class_id' => $this->ac->id,
            'status' => OrderPayment::STATUS_BACKLOG,
            'created_by_user_id' => $this->adminUser->id,
        ]);
        // OP sem AC — manual
        OrderPayment::create([
            'description' => 'Sem AC',
            'total_value' => 300,
            'date_payment' => '2026-04-15',
            'cost_center_id' => $this->cc->id,
            'status' => OrderPayment::STATUS_BACKLOG,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        [$linked, $missing] = $this->runScan(apply: true);

        $this->assertEquals(0, $linked);
        $this->assertEquals(2, $missing);
    }

    public function test_skips_ops_already_linked(): void
    {
        OrderPayment::create([
            'description' => 'Já vinculada',
            'total_value' => 500,
            'date_payment' => '2026-04-15',
            'cost_center_id' => $this->cc->id,
            'accounting_class_id' => $this->ac->id,
            'budget_item_id' => $this->budgetItem->id,
            'status' => OrderPayment::STATUS_DONE,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        [$linked, $missing] = $this->runScan(apply: true);

        $this->assertEquals(0, $linked);
        $this->assertEquals(0, $missing);
    }
}
