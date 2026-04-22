<?php

namespace Tests\Feature\Imports\DRE;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreActual;
use App\Models\DrePeriodClosing;
use App\Models\Store;
use App\Models\User;
use App\Services\DRE\DreActualsImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Cobre `App\Services\DRE\DreActualsImporter` e a ponte HTTP
 * (`DreImportController::actualsStore`).
 *
 * Planilhas são geradas em tempo de teste — não dependemos de fixtures no
 * repo. Fixtures FK (store, chart_of_accounts, cost_centers) vêm de
 * factories + create() direto.
 */
class DreActualsImportTest extends TestCase
{
    use RefreshDatabase;

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dre-actuals-import-'.uniqid().'.xlsx';
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

    public function test_imports_valid_rows(): void
    {
        $store = Store::factory()->create(['code' => 'Z998']);
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TEST.ACT.01',
            'account_group' => 4, // despesa
        ]);

        $this->writeXlsx([
            ['2027-03-15', 'Z998', 'TEST.ACT.01', '', 100.00, 'NF-1', 'teste', ''],
            ['2027-03-16', 'Z998', 'TEST.ACT.01', '', 50.00, 'NF-2', 'teste 2', ''],
        ]);

        $report = app(DreActualsImporter::class)->import($this->tmpPath);

        $this->assertSame(2, $report->totalRead);
        $this->assertSame(2, $report->created);
        $this->assertSame(0, $report->skipped);
        $this->assertEmpty($report->errors);

        // Conta é grupo 4 → sinal negativo.
        $this->assertDatabaseHas('dre_actuals', [
            'chart_of_account_id' => $account->id,
            'store_id' => $store->id,
            'amount' => -100.00,
            'source' => DreActual::SOURCE_MANUAL_IMPORT,
            'entry_date' => '2027-03-15',
        ]);
        $this->assertDatabaseHas('dre_actuals', [
            'amount' => -50.00,
            'entry_date' => '2027-03-16',
        ]);
    }

    public function test_revenue_rows_keep_positive_sign(): void
    {
        $store = Store::factory()->create(['code' => 'Z999']);
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'TEST.REV.01',
            'account_group' => 3,
        ]);

        $this->writeXlsx([
            ['2027-03-15', 'Z999', 'TEST.REV.01', '', 1000.00, '', '', ''],
        ]);

        app(DreActualsImporter::class)->import($this->tmpPath);

        $this->assertDatabaseHas('dre_actuals', [
            'amount' => 1000.00,
        ]);
    }

    // -----------------------------------------------------------------
    // Erros esperados — linhas puladas, mensagens PT-BR
    // -----------------------------------------------------------------

    public function test_missing_account_is_skipped_with_error(): void
    {
        $store = Store::factory()->create(['code' => 'Z997']);

        $this->writeXlsx([
            ['2027-03-15', 'Z997', 'TEST.UNKNOWN.99999', '', 100.00, '', '', ''],
        ]);

        $report = app(DreActualsImporter::class)->import($this->tmpPath);

        $this->assertSame(1, $report->skipped);
        $this->assertSame(0, $report->created);
        $this->assertStringContainsString(
            "conta 'TEST.UNKNOWN.99999' não encontrada",
            $report->errors[0],
        );
        $this->assertStringContainsString('Linha 2', $report->errors[0]);
    }

    public function test_synthetic_account_is_rejected(): void
    {
        Store::factory()->create(['code' => 'Z996']);
        ChartOfAccount::factory()->synthetic()->create([
            'code' => 'TEST.SYN.01',
            'account_group' => 4,
        ]);

        $this->writeXlsx([
            ['2027-03-15', 'Z996', 'TEST.SYN.01', '', 100.00, '', '', ''],
        ]);

        $report = app(DreActualsImporter::class)->import($this->tmpPath);

        $this->assertSame(1, $report->skipped);
        $this->assertStringContainsString('sintética', $report->errors[0]);
    }

    public function test_entry_date_in_closed_period_is_rejected(): void
    {
        $store = Store::factory()->create(['code' => 'Z995']);
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'TEST.CLOSED.01',
            'account_group' => 4,
        ]);

        // Fechamento ativo até 2027-02-28 (fim de fevereiro).
        $adminUser = User::factory()->create();
        DrePeriodClosing::create([
            'closed_up_to_date' => '2027-02-28',
            'closed_at' => now(),
            'closed_by_user_id' => $adminUser->id,
        ]);

        $this->writeXlsx([
            ['2027-02-15', 'Z995', 'TEST.CLOSED.01', '', 100.00, '', '', ''], // dentro do fechamento
            ['2027-03-15', 'Z995', 'TEST.CLOSED.01', '', 200.00, '', '', ''], // após — ok
        ]);

        $report = app(DreActualsImporter::class)->import($this->tmpPath);

        $this->assertSame(1, $report->skipped);
        $this->assertSame(1, $report->created);
        $this->assertStringContainsString('período fechado', $report->errors[0]);
        $this->assertStringContainsString('2027-02-28', $report->errors[0]);
    }

    public function test_invalid_store_is_rejected(): void
    {
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'TEST.STO.01',
            'account_group' => 4,
        ]);

        $this->writeXlsx([
            ['2027-03-15', 'Z000', 'TEST.STO.01', '', 100.00, '', '', ''],
        ]);

        $report = app(DreActualsImporter::class)->import($this->tmpPath);
        $this->assertSame(1, $report->skipped);
        $this->assertStringContainsString("loja 'Z000' não encontrada", $report->errors[0]);
    }

    public function test_asset_group_account_is_rejected(): void
    {
        Store::factory()->create(['code' => 'Z994']);
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'TEST.AST.01',
            'account_group' => 1, // Ativo — não pertence à DRE
        ]);

        $this->writeXlsx([
            ['2027-03-15', 'Z994', 'TEST.AST.01', '', 100.00, '', '', ''],
        ]);

        $report = app(DreActualsImporter::class)->import($this->tmpPath);
        $this->assertSame(1, $report->skipped);
        $this->assertStringContainsString('grupo 1 (Ativo/Passivo)', $report->errors[0]);
    }

    // -----------------------------------------------------------------
    // Upsert por external_id
    // -----------------------------------------------------------------

    public function test_reimport_with_same_external_id_updates(): void
    {
        $store = Store::factory()->create(['code' => 'Z993']);
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TEST.EXT.01',
            'account_group' => 4,
        ]);

        $this->writeXlsx([
            ['2027-03-15', 'Z993', 'TEST.EXT.01', '', 100.00, '', 'primeira', 'EXT-001'],
        ]);
        app(DreActualsImporter::class)->import($this->tmpPath);

        $this->assertDatabaseCount('dre_actuals', 1);

        // Segunda importação — mesmo external_id, valor diferente.
        $this->writeXlsx([
            ['2027-03-15', 'Z993', 'TEST.EXT.01', '', 200.00, '', 'corrigida', 'EXT-001'],
        ]);
        $report = app(DreActualsImporter::class)->import($this->tmpPath);

        $this->assertSame(0, $report->created);
        $this->assertSame(1, $report->updated);
        $this->assertDatabaseCount('dre_actuals', 1);
        $this->assertDatabaseHas('dre_actuals', [
            'external_id' => 'EXT-001',
            'amount' => -200.00,
            'description' => 'corrigida',
        ]);
    }

    // -----------------------------------------------------------------
    // Dry-run
    // -----------------------------------------------------------------

    public function test_dry_run_does_not_persist(): void
    {
        $store = Store::factory()->create(['code' => 'Z992']);
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'TEST.DRY.01',
            'account_group' => 4,
        ]);

        $this->writeXlsx([
            ['2027-03-15', 'Z992', 'TEST.DRY.01', '', 100.00, '', '', ''],
        ]);

        $report = app(DreActualsImporter::class)->import($this->tmpPath, dryRun: true);

        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->totalRead);
        $this->assertSame(0, $report->created);
        $this->assertDatabaseMissing('dre_actuals', ['store_id' => $store->id]);
    }

    // -----------------------------------------------------------------
    // HTTP — controller
    // -----------------------------------------------------------------

    public function test_http_endpoint_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('dre.imports.actuals.store'), [
                'file' => $this->fakeUpload(),
            ])
            ->assertStatus(403);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Escreve o XLSX de teste com o cabeçalho padrão.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function writeXlsx(array $rows): void
    {
        $headers = [
            'entry_date', 'store_code', 'account_code', 'cost_center_code',
            'amount', 'document', 'description', 'external_id',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        (new Xlsx($spreadsheet))->save($this->tmpPath);
    }

    private function fakeUpload(): \Illuminate\Http\UploadedFile
    {
        $this->writeXlsx([['2027-03-15', 'Z001', 'X', '', 1, '', '', '']]);

        return new \Illuminate\Http\UploadedFile(
            $this->tmpPath,
            'actuals.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
