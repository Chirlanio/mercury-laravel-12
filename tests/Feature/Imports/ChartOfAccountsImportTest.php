<?php

namespace Tests\Feature\Imports;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Services\DRE\ChartOfAccountsImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Cobre `App\Services\DRE\ChartOfAccountsImporter` contra XLSX gerados
 * dinamicamente nos testes (não depende da fixture de dev).
 */
class ChartOfAccountsImportTest extends TestCase
{
    use RefreshDatabase;

    private string $tmpPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dre-chart-import-test-'.uniqid().'.xlsx';
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

    public function test_imports_basic_accounts_and_cost_centers(): void
    {
        $this->writeXlsx([
            $this->masterRow(),
            // Contas grupos 1..5 — usamos codes que NÃO existem no seed
            // real do projeto para isolar do backfill pré-existente.
            $this->accountRow('TEST-I01', 'S', '1', 'TEST.1', 'ATIVO TESTE'),
            $this->accountRow('TEST-I02', 'S', '1', 'TEST.1.1', 'ATIVO CIRC TESTE'),
            $this->accountRow('TEST-I03', 'A', '1', 'TEST.1.1.01.999', 'CAIXA TESTE'),
            $this->accountRow('TEST-I04', 'A', '3', 'TEST.3.1.01.999', 'RECEITA TESTE'),
            // CC grupo 8
            $this->accountRow('TEST-CC1', 'S', '8', 'TEST.8', 'CCs TESTE'),
            $this->accountRow('TEST-CC2', 'S', '8', 'TEST.8.1', 'CCs TESTE 1'),
            $this->accountRow('TEST-CC3', 'S', '8', 'TEST.8.1.01', 'MARKETING TESTE'),
            $this->accountRow('TEST-CC4', 'A', '8', 'TEST.8.1.01.01', 'Marketing Teste Loja'),
        ]);

        $importer = app(ChartOfAccountsImporter::class);
        $report = $importer->import($this->tmpPath, source: 'CIGAM');

        $this->assertSame(1, $report->ignoredMasterRow);
        $this->assertSame(4, $report->accountsCreated);
        $this->assertSame(4, $report->costCentersCreated);
        $this->assertEmpty($report->readErrors);

        $this->assertDatabaseHas('chart_of_accounts', [
            'reduced_code' => 'TEST-I03',
            'code' => 'TEST.1.1.01.999',
            'name' => 'CAIXA TESTE',
            'type' => 'analytical',
            'account_group' => 1,
            'classification_level' => 4,
            'external_source' => 'CIGAM',
        ]);

        $this->assertDatabaseHas('cost_centers', [
            'reduced_code' => 'TEST-CC3',
            'code' => 'TEST.8.1.01',
            'name' => 'MARKETING TESTE',
            'external_source' => 'CIGAM',
        ]);
    }

    public function test_matches_existing_account_by_code_when_reduced_code_is_null(): void
    {
        // Caso típico de convivência com seed legado: alguém criou uma
        // conta sem reduced_code (seed antigo). O XLSX traz ela agora
        // com reduced_code definido. Queremos que o importer CASE por
        // code e ATUALIZE, em vez de criar duplicata (UNIQUE de code
        // bloquearia de qualquer forma).
        ChartOfAccount::create([
            'code' => 'LEGACY.9.9.99.99999',
            'reduced_code' => null,
            'name' => 'Legacy sem reduced',
            'type' => 'analytical',
            'account_group' => 4,
            'classification_level' => 4,
            'accepts_entries' => true,
            'is_active' => true,
        ]);

        $this->writeXlsx([
            $this->accountRow('LEG-999', 'A', '4', 'LEGACY.9.9.99.99999', 'Legacy com reduced'),
        ]);

        $report = app(ChartOfAccountsImporter::class)->import($this->tmpPath, source: 'CIGAM');

        $this->assertSame(0, $report->accountsCreated);
        $this->assertSame(1, $report->accountsUpdated);

        $this->assertSame(1, ChartOfAccount::where('code', 'LEGACY.9.9.99.99999')->count());
        $this->assertDatabaseHas('chart_of_accounts', [
            'code' => 'LEGACY.9.9.99.99999',
            'reduced_code' => 'LEG-999',
            'name' => 'Legacy com reduced',
            'external_source' => 'CIGAM',
        ]);
    }

    public function test_group_8_rows_go_to_cost_centers_not_chart_of_accounts(): void
    {
        $this->writeXlsx([
            $this->accountRow('8121', 'S', '8', '8.1.01', 'MARKETING'),
            $this->accountRow('8122', 'A', '8', '8.1.01.01', 'Marketing - Schutz Riomar'),
        ]);

        app(ChartOfAccountsImporter::class)->import($this->tmpPath);

        $this->assertDatabaseMissing('chart_of_accounts', ['reduced_code' => '8121']);
        $this->assertDatabaseMissing('chart_of_accounts', ['reduced_code' => '8122']);

        $this->assertDatabaseHas('cost_centers', ['reduced_code' => '8121']);
        $this->assertDatabaseHas('cost_centers', ['reduced_code' => '8122']);
    }

    // -----------------------------------------------------------------
    // parent_id
    // -----------------------------------------------------------------

    public function test_parent_id_is_resolved_by_code_prefix(): void
    {
        $this->writeXlsx([
            $this->accountRow('1000', 'S', '1', '1', 'ATIVO'),
            $this->accountRow('1001', 'S', '1', '1.1', 'ATIVO CIRCULANTE'),
            $this->accountRow('1002', 'S', '1', '1.1.1', 'DISPONIVEL'),
            $this->accountRow('1003', 'S', '1', '1.1.1.01', 'CAIXA GERAL'),
            $this->accountRow('1004', 'A', '1', '1.1.1.01.00016', 'CAIXA TESOURARIA'),
        ]);

        app(ChartOfAccountsImporter::class)->import($this->tmpPath);

        $root = ChartOfAccount::where('code', '1')->firstOrFail();
        $level1 = ChartOfAccount::where('code', '1.1')->firstOrFail();
        $level2 = ChartOfAccount::where('code', '1.1.1')->firstOrFail();
        $level3 = ChartOfAccount::where('code', '1.1.1.01')->firstOrFail();
        $leaf = ChartOfAccount::where('code', '1.1.1.01.00016')->firstOrFail();

        $this->assertNull($root->parent_id);
        $this->assertSame($root->id, $level1->parent_id);
        $this->assertSame($level1->id, $level2->parent_id);
        $this->assertSame($level2->id, $level3->parent_id);
        $this->assertSame($level3->id, $leaf->parent_id);
    }

    public function test_orphan_analytical_produces_warning_but_does_not_break(): void
    {
        // Só a folha — sem ancestrais (1, 1.1, 1.1.1, etc).
        $this->writeXlsx([
            $this->accountRow('1004', 'A', '1', '1.1.1.01.00016', 'CAIXA TESOURARIA'),
        ]);

        $report = app(ChartOfAccountsImporter::class)->import($this->tmpPath);

        $this->assertSame(1, $report->accountsCreated);
        $this->assertNotEmpty($report->orphanWarnings);
        $this->assertStringContainsString('1.1.1.01.00016', $report->orphanWarnings[0]);

        $leaf = ChartOfAccount::where('code', '1.1.1.01.00016')->firstOrFail();
        $this->assertNull($leaf->parent_id);
    }

    // -----------------------------------------------------------------
    // Idempotência
    // -----------------------------------------------------------------

    public function test_reimport_same_file_produces_no_duplicates_and_no_errors(): void
    {
        $this->writeXlsx([
            $this->accountRow('1004', 'A', '1', '1.1.1.01.00016', 'CAIXA TESOURARIA'),
            $this->accountRow('8121', 'S', '8', '8.1.01', 'MARKETING'),
        ]);

        $importer = app(ChartOfAccountsImporter::class);

        $first = $importer->import($this->tmpPath);
        $second = $importer->import($this->tmpPath);

        $this->assertSame(1, $first->accountsCreated);
        $this->assertSame(1, $first->costCentersCreated);

        $this->assertSame(0, $second->accountsCreated);
        $this->assertSame(0, $second->costCentersCreated);
        $this->assertEmpty($second->readErrors);

        $this->assertSame(1, ChartOfAccount::where('reduced_code', '1004')->count());
        $this->assertSame(1, CostCenter::where('reduced_code', '8121')->count());
    }

    public function test_updates_fields_on_reimport_when_name_changes(): void
    {
        $this->writeXlsx([
            $this->accountRow('1004', 'A', '1', '1.1.1.01.00016', 'CAIXA TESOURARIA'),
        ]);
        $importer = app(ChartOfAccountsImporter::class);
        $importer->import($this->tmpPath);

        // Nome mudou no arquivo novo.
        $this->writeXlsx([
            $this->accountRow('1004', 'A', '1', '1.1.1.01.00016', 'CAIXA TESOURARIA GERAL'),
        ]);
        $report = $importer->import($this->tmpPath);

        $this->assertSame(0, $report->accountsCreated);
        $this->assertSame(1, $report->accountsUpdated);
        $this->assertDatabaseHas('chart_of_accounts', [
            'reduced_code' => '1004',
            'name' => 'CAIXA TESOURARIA GERAL',
        ]);
    }

    // -----------------------------------------------------------------
    // Desativação por sumiço
    // -----------------------------------------------------------------

    public function test_account_missing_from_new_file_is_deactivated(): void
    {
        // Primeira importação inclui duas contas.
        $this->writeXlsx([
            $this->accountRow('1004', 'A', '1', '1.1.1.01.00016', 'CAIXA TESOURARIA'),
            $this->accountRow('1005', 'A', '1', '1.1.1.01.00017', 'CAIXA LOJAS'),
        ]);
        $importer = app(ChartOfAccountsImporter::class);
        $importer->import($this->tmpPath);

        // Segunda importação omite a 1005 — deve virar is_active=false.
        $this->writeXlsx([
            $this->accountRow('1004', 'A', '1', '1.1.1.01.00016', 'CAIXA TESOURARIA'),
        ]);
        $report = $importer->import($this->tmpPath);

        $this->assertSame(1, $report->accountsDeactivatedByRemoval);

        $this->assertDatabaseHas('chart_of_accounts', [
            'reduced_code' => '1005',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('chart_of_accounts', [
            'reduced_code' => '1004',
            'is_active' => true,
        ]);
    }

    public function test_manual_entries_without_source_are_not_deactivated(): void
    {
        // Cria uma conta "manual" (sem external_source).
        ChartOfAccount::create([
            'reduced_code' => 'MANUAL-A',
            'code' => 'TEST.MANUAL',
            'name' => 'Criada manualmente',
            'type' => 'analytical',
            'account_group' => 4,
            'classification_level' => 2,
            'accepts_entries' => true,
            'is_active' => true,
            'external_source' => null, // manual
        ]);

        // Import com arquivo vazio (só linha-mestre, nada a atualizar).
        $this->writeXlsx([$this->masterRow()]);
        app(ChartOfAccountsImporter::class)->import($this->tmpPath);

        $this->assertDatabaseHas('chart_of_accounts', [
            'reduced_code' => 'MANUAL-A',
            'is_active' => true,
        ]);
    }

    // -----------------------------------------------------------------
    // Dry-run
    // -----------------------------------------------------------------

    public function test_dry_run_does_not_persist_but_estimates(): void
    {
        $this->writeXlsx([
            $this->accountRow('1004', 'A', '1', '1.1.1.01.00016', 'CAIXA TESOURARIA'),
            $this->accountRow('8121', 'S', '8', '8.1.01', 'MARKETING'),
        ]);

        $report = app(ChartOfAccountsImporter::class)->import($this->tmpPath, dryRun: true);

        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->accountsCreated);
        $this->assertSame(1, $report->costCentersCreated);

        // Banco continua vazio.
        $this->assertDatabaseMissing('chart_of_accounts', ['reduced_code' => '1004']);
        $this->assertDatabaseMissing('cost_centers', ['reduced_code' => '8121']);
    }

    // -----------------------------------------------------------------
    // Helpers de fixture
    // -----------------------------------------------------------------

    /** Linha-raiz do plano — deve ser ignorada pelo importador. */
    private function masterRow(): array
    {
        return ['3308', '3308', 'A', null, null, null, null, null, 'True', 'A', '0', 'A', 'I', 'False', null, null, 'False', null, null, null, null];
    }

    private function accountRow(
        string $reducedCode,
        string $type,
        string $group,
        string $code,
        string $name,
        string $natureza = 'A',
        string $ativa = 'True',
        string $resultado = 'False',
    ): array {
        return [
            $reducedCode, '3308', $type, $group, $code, $name, null, null,
            $ativa, $natureza, '0', 'A', 'I', $resultado,
            null, null, 'False', null, null, null, null,
        ];
    }

    private function writeXlsx(array $rows): void
    {
        $headers = [
            'Codigo Reduzido', 'V_Codigo plan.con', 'Tipo', 'V_Grupo',
            'Classific conta', 'Nome conta', 'Codigo Alternativo', 'Livre 14',
            'VL_Ativa', 'Natureza Saldo', 'Unidade Resultado', 'Saldo demons acu',
            'Origem conta', 'VL_Conta Resultado', 'V_Tipo LALUR',
            'V_Código Fixo LALUR', 'VL_Parte B LALUR', 'V_Funcao conta',
            'V_Funcionamento conta', 'V_Naturez Subconta', 'DescNatureza',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        (new Xlsx($spreadsheet))->save($this->tmpPath);
    }
}
