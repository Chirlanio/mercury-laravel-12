<?php

namespace Tests\Feature\Imports;

use App\Models\ChartOfAccount;
use App\Models\ManagementClass;
use App\Services\DRE\ActionPlanHintImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ActionPlanHintImporterTest extends TestCase
{
    use RefreshDatabase;

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'action-plan-hint-test-'.uniqid().'.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpPath)) {
            @unlink($this->tmpPath);
        }
        parent::tearDown();
    }

    // -----------------------------------------------------------------
    // Arquivo ausente
    // -----------------------------------------------------------------

    public function test_missing_file_returns_file_not_found_without_exception(): void
    {
        $importer = app(ActionPlanHintImporter::class);

        $report = $importer->populateDefaultManagementClass('/path/that/does/not/exist.xlsx');

        $this->assertTrue($report->fileNotFound);
        $this->assertSame(0, $report->totalRowsRead);
    }

    // -----------------------------------------------------------------
    // Import básico
    // -----------------------------------------------------------------

    public function test_populates_hint_when_both_chart_and_mgmt_exist(): void
    {
        $chart = ChartOfAccount::factory()->analytical()->create(['code' => 'TEST.HINT.01']);
        $mgmt = ManagementClass::create([
            'code' => 'TEST.MGT.01',
            'name' => 'Hint gerencial',
            'accepts_entries' => true,
            'is_active' => true,
        ]);

        $this->writeFixture([
            ['TEST.HINT.01', 'TEST.MGT.01'],
        ]);

        $report = app(ActionPlanHintImporter::class)->populateDefaultManagementClass($this->tmpPath);

        $this->assertFalse($report->fileNotFound);
        $this->assertSame(1, $report->uniquePairsFound);
        $this->assertSame(1, $report->accountsUpdated);

        $this->assertSame(
            $mgmt->id,
            $chart->fresh()->default_management_class_id
        );
    }

    // -----------------------------------------------------------------
    // Não sobrescreve valor já populado
    // -----------------------------------------------------------------

    public function test_does_not_overwrite_existing_hint(): void
    {
        $mgmt1 = ManagementClass::create([
            'code' => 'TEST.MGT.10',
            'name' => 'Mgmt original',
            'accepts_entries' => true,
            'is_active' => true,
        ]);
        $mgmt2 = ManagementClass::create([
            'code' => 'TEST.MGT.20',
            'name' => 'Mgmt action plan',
            'accepts_entries' => true,
            'is_active' => true,
        ]);

        $chart = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TEST.HINT.OVW',
            'default_management_class_id' => $mgmt1->id,
        ]);

        $this->writeFixture([
            ['TEST.HINT.OVW', 'TEST.MGT.20'],
        ]);

        $report = app(ActionPlanHintImporter::class)->populateDefaultManagementClass($this->tmpPath);

        $this->assertSame(0, $report->accountsUpdated);
        $this->assertSame(1, $report->accountsSkippedAlreadyHinted);

        $this->assertSame(
            $mgmt1->id,
            $chart->fresh()->default_management_class_id,
            'Valor manual anterior deveria ser preservado.'
        );
    }

    // -----------------------------------------------------------------
    // Deduplicação de pares
    // -----------------------------------------------------------------

    public function test_dedupes_pairs_across_rows(): void
    {
        $chart = ChartOfAccount::factory()->analytical()->create(['code' => 'TEST.HINT.DUP']);
        $mgmt = ManagementClass::create([
            'code' => 'TEST.MGT.DUP',
            'name' => 'Mgmt dup',
            'accepts_entries' => true,
            'is_active' => true,
        ]);

        // 3 linhas com o mesmo par — deve contar como 1 par único.
        $this->writeFixture([
            ['TEST.HINT.DUP', 'TEST.MGT.DUP'],
            ['TEST.HINT.DUP', 'TEST.MGT.DUP'],
            ['TEST.HINT.DUP', 'TEST.MGT.DUP'],
        ]);

        $report = app(ActionPlanHintImporter::class)->populateDefaultManagementClass($this->tmpPath);

        $this->assertSame(3, $report->totalRowsRead);
        $this->assertSame(1, $report->uniquePairsFound);
        $this->assertSame(1, $report->accountsUpdated);
    }

    // -----------------------------------------------------------------
    // Códigos ausentes
    // -----------------------------------------------------------------

    public function test_reports_missing_account_and_missing_mgmt(): void
    {
        $this->writeFixture([
            ['TEST.NO.ACC.01', 'TEST.NO.MGT.01'],
            ['TEST.NO.ACC.02', 'TEST.NO.MGT.02'],
        ]);

        $report = app(ActionPlanHintImporter::class)->populateDefaultManagementClass($this->tmpPath);

        $this->assertSame(2, $report->uniquePairsFound);
        $this->assertSame(0, $report->accountsUpdated);
        $this->assertGreaterThanOrEqual(2, $report->accountsNotFound);

        $this->assertNotEmpty($report->missingAccountCodes);
        $this->assertContains('TEST.NO.ACC.01', $report->missingAccountCodes);
    }

    // -----------------------------------------------------------------
    // Helper — gera XLSX pequeno com layout do Action Plan real.
    // -----------------------------------------------------------------

    /**
     * @param  array<int,array{0:string,1:string}>  $pairs  [conta_code, mgmt_code]
     */
    private function writeFixture(array $pairs): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header com nomes iguais ao Action Plan real (não usamos o header no
        // importer — lemos por letra de coluna — mas mantemos para fidelidade).
        $sheet->fromArray([
            [
                'Código Loja Gerencial',   // A
                'Unidade de Negocio',       // B
                'Nome Loja',                // C
                'Class contabil',           // D
                'Class Gerencial',          // E
                'Descricao contabil',       // F
                'Mês',                      // G
                'Ano',                      // H
                'Valor',                    // I
            ],
        ], null, 'A1');

        $dataRows = [];
        foreach ($pairs as [$accountCode, $mgmtCode]) {
            $dataRows[] = ['421', '421', 'Loja Fake', $accountCode, $mgmtCode, 'DESC', '1', '2026', '1000'];
        }
        $sheet->fromArray($dataRows, null, 'A2');

        (new Xlsx($spreadsheet))->save($this->tmpPath);
    }
}
