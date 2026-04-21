<?php

namespace Tests\Feature;

use App\Exports\BudgetExport;
use App\Models\AccountingClass;
use App\Models\BudgetItem;
use App\Models\BudgetUpload;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\OrderPayment;
use App\Services\BudgetConsumptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre o export xlsx multi-sheet da Fase 6:
 *   - Rota /budgets/{budget}/export retorna arquivo 200
 *   - xlsx tem 6 sheets (Resumo, Por CC, Por AC, Por Área, Por Mês, Detalhe)
 *   - Totais batem com BudgetConsumptionService
 */
class BudgetExportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected BudgetUpload $budget;

    protected BudgetItem $item1;

    protected ManagementClass $areaDepartment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        app(\App\Services\CentralRoleResolver::class)->clearCache();

        $ac = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail();
        $cc = CostCenter::create([
            'code' => 'CC-EXP', 'name' => 'CC Export',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->areaDepartment = ManagementClass::where('code', '8.1.01')->firstOrFail();

        $mc = ManagementClass::create([
            'code' => 'MC-EXP', 'name' => 'MC Export',
            'accepts_entries' => true,
            'accounting_class_id' => $ac->id,
            'cost_center_id' => $cc->id,
            'parent_id' => $this->areaDepartment->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->budget = BudgetUpload::create([
            'year' => 2026, 'scope_label' => 'ExportTest', 'version_label' => '1.0',
            'major_version' => 1, 'minor_version' => 0, 'upload_type' => 'novo',
            'area_department_id' => $this->areaDepartment->id,
            'original_filename' => 't.xlsx', 'stored_path' => 'budgets/2026/t.xlsx',
            'file_size_bytes' => 1, 'is_active' => true, 'total_year' => 12000,
            'items_count' => 1,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $this->item1 = BudgetItem::create([
            'budget_upload_id' => $this->budget->id,
            'accounting_class_id' => $ac->id,
            'management_class_id' => $mc->id,
            'cost_center_id' => $cc->id,
            'month_01_value' => 1000, 'month_02_value' => 1000, 'month_03_value' => 1000,
            'month_04_value' => 1000, 'month_05_value' => 1000, 'month_06_value' => 1000,
            'month_07_value' => 1000, 'month_08_value' => 1000, 'month_09_value' => 1000,
            'month_10_value' => 1000, 'month_11_value' => 1000, 'month_12_value' => 1000,
            'year_total' => 12000,
        ]);

        // OP pagando parte do budget — para testar realized no export
        OrderPayment::create([
            'description' => 'Paga',
            'total_value' => 500,
            'date_payment' => '2026-03-10',
            'cost_center_id' => $cc->id,
            'accounting_class_id' => $ac->id,
            'budget_item_id' => $this->item1->id,
            'status' => OrderPayment::STATUS_DONE,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    public function test_export_returns_xlsx_file(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('budgets.export', $this->budget));

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type') ?? ''
        );
        $this->assertStringContainsString(
            'orcamento_exporttest_2026_v1.0',
            $response->headers->get('Content-Disposition') ?? ''
        );
    }

    public function test_export_xlsx_has_6_sheets_with_expected_titles(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'budget_export_');

        \Illuminate\Support\Facades\Storage::put(
            'tmp-export.xlsx',
            BudgetExport::fromBudget($this->budget, app(BudgetConsumptionService::class))
                ->raw(\Maatwebsite\Excel\Excel::XLSX)
        );

        $raw = BudgetExport::fromBudget($this->budget, app(BudgetConsumptionService::class))
            ->raw(\Maatwebsite\Excel\Excel::XLSX);
        file_put_contents($path, $raw);

        $spreadsheet = IOFactory::load($path);
        $sheetNames = $spreadsheet->getSheetNames();

        $this->assertContains('Resumo', $sheetNames);
        $this->assertContains('Por Centro de Custo', $sheetNames);
        $this->assertContains('Por Conta Contábil', $sheetNames);
        $this->assertContains('Por Área', $sheetNames);
        $this->assertContains('Por Mês', $sheetNames);
        $this->assertContains('Detalhe por Item', $sheetNames);
        $this->assertCount(6, $sheetNames);

        // Valida alguns valores-chave
        $summary = $spreadsheet->getSheetByName('Resumo');
        $this->assertEquals(12000, (float) $summary->getCell('B8')->getValue()); // Previsto total
        $this->assertEquals(500, (float) $summary->getCell('B10')->getValue()); // Realizado

        $byArea = $spreadsheet->getSheetByName('Por Área');
        $this->assertEquals(
            $this->areaDepartment->name,
            (string) $byArea->getCell('A2')->getValue()
        );

        @unlink($path);
    }

    public function test_export_empty_budget_still_produces_valid_xlsx(): void
    {
        // Budget sem items
        $empty = BudgetUpload::create([
            'year' => 2027, 'scope_label' => 'EmptyExport', 'version_label' => '1.0',
            'major_version' => 1, 'minor_version' => 0, 'upload_type' => 'novo',
            'area_department_id' => $this->areaDepartment->id,
            'original_filename' => 'e.xlsx', 'stored_path' => 'budgets/2027/e.xlsx',
            'file_size_bytes' => 1, 'is_active' => true, 'total_year' => 0,
            'items_count' => 0,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('budgets.export', $empty));

        $response->assertStatus(200);
    }

    public function test_export_requires_permission(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('budgets.export', $this->budget));

        $response->assertStatus(403);
    }
}
