<?php

namespace Tests\Feature\Imports\DRE;

use App\Models\ChartOfAccount;
use App\Models\Store;
use App\Services\DRE\DreBudgetsImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Cobre `App\Services\DRE\DreBudgetsImporter` — análogo aos actuals mas
 * para orçado (dre_budgets), sem checagem de fechamento e sem external_id.
 */
class DreBudgetsImportTest extends TestCase
{
    use RefreshDatabase;

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dre-budgets-import-'.uniqid().'.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpPath)) {
            @unlink($this->tmpPath);
        }
        parent::tearDown();
    }

    public function test_imports_rows_normalizing_to_first_of_month(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'BUD.EXP.01',
            'account_group' => 4,
        ]);

        // Aceita YYYY-MM sem dia e normaliza para dia 1.
        $this->writeXlsx([
            ['2027-03',    '', 'BUD.EXP.01', '', 1500.00, 'nota jan'],
            ['2027-04-17', '', 'BUD.EXP.01', '', 2000.00, 'nota fev'],
        ]);

        $report = app(DreBudgetsImporter::class)->import($this->tmpPath, '2027.v1');

        $this->assertSame(2, $report->totalRead);
        $this->assertSame(2, $report->created);
        $this->assertSame('2027.v1', $report->budgetVersion);
        $this->assertEmpty($report->errors);

        $this->assertDatabaseHas('dre_budgets', [
            'chart_of_account_id' => $account->id,
            'entry_date' => '2027-03-01',
            'amount' => -1500.00,
            'budget_version' => '2027.v1',
        ]);
        $this->assertDatabaseHas('dre_budgets', [
            'entry_date' => '2027-04-01', // normalizado
            'amount' => -2000.00,
        ]);
    }

    public function test_requires_budget_version(): void
    {
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'BUD.EXP.02',
            'account_group' => 4,
        ]);

        $this->writeXlsx([
            ['2027-03', '', 'BUD.EXP.02', '', 100.00, ''],
        ]);

        $report = app(DreBudgetsImporter::class)->import($this->tmpPath, '');

        $this->assertSame(0, $report->created);
        $this->assertNotEmpty($report->errors);
        $this->assertStringContainsString('budget_version', $report->errors[0]);
    }

    public function test_nullable_store_code_accepted(): void
    {
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'BUD.REV.01',
            'account_group' => 3,
        ]);

        $this->writeXlsx([
            ['2027-03', '', 'BUD.REV.01', '', 8000.00, 'consolidado'],
        ]);

        app(DreBudgetsImporter::class)->import($this->tmpPath, '2027.v1');

        $this->assertDatabaseHas('dre_budgets', [
            'store_id' => null,
            'amount' => 8000.00,
        ]);
    }

    public function test_missing_account_is_skipped(): void
    {
        $this->writeXlsx([
            ['2027-03', '', 'NOPE.999', '', 100.00, ''],
        ]);

        $report = app(DreBudgetsImporter::class)->import($this->tmpPath, '2027.v1');
        $this->assertSame(1, $report->skipped);
        $this->assertStringContainsString("conta 'NOPE.999'", $report->errors[0]);
    }

    public function test_unknown_store_is_skipped(): void
    {
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'BUD.EXP.03',
            'account_group' => 4,
        ]);

        $this->writeXlsx([
            ['2027-03', 'Z000', 'BUD.EXP.03', '', 100.00, ''],
        ]);

        $report = app(DreBudgetsImporter::class)->import($this->tmpPath, '2027.v1');
        $this->assertSame(1, $report->skipped);
        $this->assertStringContainsString("loja 'Z000'", $report->errors[0]);
    }

    public function test_ignores_period_closing_unlike_actuals(): void
    {
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'BUD.OLD.01',
            'account_group' => 4,
        ]);

        // Fechamento até 2027-06 — orçado deve ignorar.
        $u = \App\Models\User::factory()->create();
        \App\Models\DrePeriodClosing::create([
            'closed_up_to_date' => '2027-06-30',
            'closed_at' => now(),
            'closed_by_user_id' => $u->id,
        ]);

        $this->writeXlsx([
            ['2027-01', '', 'BUD.OLD.01', '', 100.00, ''], // dentro do fechamento
        ]);

        $report = app(DreBudgetsImporter::class)->import($this->tmpPath, '2027.v1');
        $this->assertSame(1, $report->created);
        $this->assertSame(0, $report->skipped);
    }

    private function writeXlsx(array $rows): void
    {
        $headers = [
            'entry_date', 'store_code', 'account_code', 'cost_center_code',
            'amount', 'notes',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        (new Xlsx($spreadsheet))->save($this->tmpPath);
    }
}
