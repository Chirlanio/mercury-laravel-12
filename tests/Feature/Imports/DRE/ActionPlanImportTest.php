<?php

namespace Tests\Feature\Imports\DRE;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreBudget;
use App\Models\Store;
use App\Services\DRE\ActionPlanImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Cobre `App\Services\DRE\ActionPlanImporter` contra XLSX fixture mínima.
 *
 * A planilha real tem 3861 linhas — os testes usam 2..5 linhas para cobrir
 * FKs, idempotência e erros. FK data é criada com factory + create() direto.
 */
class ActionPlanImportTest extends TestCase
{
    use RefreshDatabase;

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'action-plan-'.uniqid().'.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpPath)) {
            @unlink($this->tmpPath);
        }
        parent::tearDown();
    }

    // -----------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------

    public function test_small_fixture_imports_all_rows(): void
    {
        [$store, $cc, $account] = $this->scaffold();

        $this->writeXlsx([
            [$cc->code, $cc->code, $store->name, $account->code, '8.1.09.01', 'RECEITA', 1, 2026, 10000.00],
            [$cc->code, $cc->code, $store->name, $account->code, '8.1.09.01', 'RECEITA', 2, 2026, 12000.00],
        ]);

        $report = app(ActionPlanImporter::class)->import($this->tmpPath);

        $this->assertSame(2, $report->totalRead);
        $this->assertSame(2, $report->inserted);
        $this->assertSame(0, $report->updated);
        $this->assertSame(0, $report->skipped);
        $this->assertEmpty($report->errors);

        $this->assertDatabaseHas('dre_budgets', [
            'entry_date' => '2026-01-01',
            'chart_of_account_id' => $account->id,
            'cost_center_id' => $cc->id,
            'store_id' => $store->id,
            'amount' => 10000.00,
            'budget_version' => 'action_plan_v1',
        ]);
        $this->assertDatabaseHas('dre_budgets', [
            'entry_date' => '2026-02-01',
            'amount' => 12000.00,
        ]);
    }

    public function test_expense_account_gets_negative_sign(): void
    {
        [$store, $cc, $_] = $this->scaffold();
        $expense = ChartOfAccount::factory()->analytical()->create([
            'code' => 'AP.EXP.01',
            'account_group' => 4,
        ]);

        $this->writeXlsx([
            [$cc->code, $cc->code, $store->name, $expense->code, '8.1.01.01', 'DESPESA', 3, 2026, 500.00],
        ]);

        app(ActionPlanImporter::class)->import($this->tmpPath);

        $this->assertDatabaseHas('dre_budgets', [
            'chart_of_account_id' => $expense->id,
            'amount' => -500.00,
        ]);
    }

    // -----------------------------------------------------------------
    // Idempotência
    // -----------------------------------------------------------------

    public function test_reimport_same_file_is_idempotent(): void
    {
        [$store, $cc, $account] = $this->scaffold();

        $this->writeXlsx([
            [$cc->code, $cc->code, $store->name, $account->code, '8.1.09.01', 'RECEITA', 1, 2026, 10000.00],
        ]);

        app(ActionPlanImporter::class)->import($this->tmpPath);
        $this->assertDatabaseCount('dre_budgets', 1);

        // Reimport — mesma chave composta → update, não insert.
        $report2 = app(ActionPlanImporter::class)->import($this->tmpPath);
        $this->assertSame(1, $report2->totalRead);
        $this->assertSame(0, $report2->inserted);
        $this->assertSame(1, $report2->updated);
        $this->assertDatabaseCount('dre_budgets', 1);
    }

    public function test_reimport_with_new_amount_updates_existing_row(): void
    {
        [$store, $cc, $account] = $this->scaffold();

        $this->writeXlsx([
            [$cc->code, $cc->code, $store->name, $account->code, '8.1.09.01', 'RECEITA', 1, 2026, 10000.00],
        ]);
        app(ActionPlanImporter::class)->import($this->tmpPath);

        // Mesmo file, valor trocado simula "CFO corrigiu e reimportou".
        $this->writeXlsx([
            [$cc->code, $cc->code, $store->name, $account->code, '8.1.09.01', 'RECEITA', 1, 2026, 99999.00],
        ]);
        app(ActionPlanImporter::class)->import($this->tmpPath);

        $this->assertDatabaseCount('dre_budgets', 1);
        $this->assertDatabaseHas('dre_budgets', [
            'amount' => 99999.00,
            'budget_version' => 'action_plan_v1',
        ]);
    }

    public function test_new_version_label_coexists_with_previous(): void
    {
        [$store, $cc, $account] = $this->scaffold();

        $this->writeXlsx([
            [$cc->code, $cc->code, $store->name, $account->code, '8.1.09.01', 'RECEITA', 1, 2026, 10000.00],
        ]);
        app(ActionPlanImporter::class)->import($this->tmpPath, 'action_plan_v1');

        // Mesmo arquivo, version diferente → nova linha.
        app(ActionPlanImporter::class)->import($this->tmpPath, 'action_plan_v2');

        $this->assertDatabaseCount('dre_budgets', 2);
        $this->assertSame(1, DreBudget::where('budget_version', 'action_plan_v1')->count());
        $this->assertSame(1, DreBudget::where('budget_version', 'action_plan_v2')->count());
    }

    // -----------------------------------------------------------------
    // Erros esperados (linha pulada, msg PT-BR)
    // -----------------------------------------------------------------

    public function test_unknown_account_is_skipped_with_error(): void
    {
        [$store, $cc, $_] = $this->scaffold();

        $this->writeXlsx([
            [$cc->code, $cc->code, $store->name, 'NOPE.99.99', '8.1.09.01', 'x', 1, 2026, 100.00],
        ]);

        $report = app(ActionPlanImporter::class)->import($this->tmpPath);
        $this->assertSame(1, $report->skipped);
        $this->assertStringContainsString("conta contábil 'NOPE.99.99'", $report->errors[0]);
    }

    public function test_unknown_store_code_imports_without_store_and_cc(): void
    {
        [$_, $__, $account] = $this->scaffold();

        $this->writeXlsx([
            ['999', '999', 'Loja Nova', $account->code, '8.1.09.01', 'RECEITA', 1, 2026, 1000.00],
        ]);

        $report = app(ActionPlanImporter::class)->import($this->tmpPath);

        // Store nem CC resolvem — linha entra consolidada (sem FK), não é erro.
        $this->assertSame(1, $report->inserted);
        $this->assertSame(0, $report->skipped);
        $this->assertDatabaseHas('dre_budgets', [
            'chart_of_account_id' => $account->id,
            'store_id' => null,
            'cost_center_id' => null,
            'amount' => 1000.00,
        ]);
    }

    public function test_invalid_month_year_is_skipped(): void
    {
        [$store, $cc, $account] = $this->scaffold();

        $this->writeXlsx([
            [$cc->code, $cc->code, $store->name, $account->code, '8.1.09.01', 'x', 13, 2026, 100.00],
        ]);

        $report = app(ActionPlanImporter::class)->import($this->tmpPath);
        $this->assertSame(1, $report->skipped);
        $this->assertStringContainsString('mês/ano fora do intervalo', $report->errors[0]);
    }

    // -----------------------------------------------------------------
    // Dry-run
    // -----------------------------------------------------------------

    public function test_dry_run_does_not_persist(): void
    {
        [$store, $cc, $account] = $this->scaffold();

        $this->writeXlsx([
            [$cc->code, $cc->code, $store->name, $account->code, '8.1.09.01', 'x', 1, 2026, 10000.00],
        ]);

        $report = app(ActionPlanImporter::class)->import($this->tmpPath, dryRun: true);

        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->totalRead);
        $this->assertSame(0, $report->inserted);
        $this->assertDatabaseCount('dre_budgets', 0);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Cria uma Store "Z<code>", um CostCenter "<code>" compatível e uma
     * conta de receita. Ambos compartilham o código numérico que sai da
     * planilha real (ex: "421").
     *
     * @return array{0: Store, 1: CostCenter, 2: ChartOfAccount}
     */
    private function scaffold(): array
    {
        $code = (string) fake()->unique()->numberBetween(700, 999);
        $store = Store::factory()->create(['code' => 'Z'.$code]);
        $cc = CostCenter::create([
            'code' => $code,
            'name' => 'CC '.$code,
            'is_active' => true,
        ]);
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'AP.REV.'.fake()->unique()->numerify('###'),
            'account_group' => 3,
        ]);

        return [$store, $cc, $account];
    }

    private function writeXlsx(array $rows): void
    {
        $headers = [
            'Código Loja Gerencial', 'Unidade de Negocio', 'Nome Loja',
            'Class contabil', 'Class Gerencial', 'Descricao contabil',
            'Mês', 'Ano', 'Valor',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        (new Xlsx($spreadsheet))->save($this->tmpPath);
    }
}
