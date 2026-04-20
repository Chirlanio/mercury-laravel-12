<?php

namespace Tests\Feature;

use App\Models\AccountingClass;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\Store;
use App\Services\BudgetImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BudgetImportServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AccountingClass $ac1;

    protected AccountingClass $ac2;

    protected ManagementClass $mc1;

    protected CostCenter $cc1;

    protected CostCenter $cc2;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        // Reutiliza plano real (Grupo Meia Sola) já populado pelo seed
        $this->ac1 = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail(); // Telefonia
        $this->ac2 = AccountingClass::where('code', '4.2.1.04.00083')->firstOrFail(); // Outras Despesas

        $this->cc1 = CostCenter::create([
            'code' => 'CC-001',
            'name' => 'Administrativo',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->cc2 = CostCenter::create([
            'code' => 'CC-002',
            'name' => 'TI',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->mc1 = ManagementClass::create([
            'code' => 'MC-TI',
            'name' => 'Tecnologia',
            'accepts_entries' => true,
            'accounting_class_id' => $this->ac1->id,
            'cost_center_id' => $this->cc2->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->store = Store::factory()->create(['code' => 'Z100', 'name' => 'Matriz']);
    }

    protected function makeXlsx(array $rows, array $headings): string
    {
        $export = new class($rows, $headings) implements FromArray, WithHeadings
        {
            public function __construct(public array $rows, public array $headings) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        };

        $filename = 'budget-import-'.uniqid().'.xlsx';
        Excel::store($export, $filename, 'local');

        $stored = storage_path('app/private/'.$filename);
        if (! file_exists($stored)) {
            $stored = storage_path('app/'.$filename);
        }

        return $stored;
    }

    public function test_parses_valid_row_with_all_fks_resolved(): void
    {
        $service = app(BudgetImportService::class);

        $path = $this->makeXlsx([
            ['4.2.1.04.00032', 'MC-TI', 'CC-001', 'Z100', 'Fornecedor X', '', '', '', 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000],
        ], [
            'codigo_contabil', 'codigo_gerencial', 'codigo_centro_custo', 'codigo_loja',
            'fornecedor', 'justificativa', 'descricao_conta', 'descricao_classe',
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ]);

        $result = $service->preview($path);

        $this->assertEquals(1, $result['total_rows']);
        $this->assertEquals(1, $result['valid_rows']);
        $this->assertEquals(0, $result['needs_reconciliation']);
        $this->assertEquals(0, $result['rejected_rows']);
        $this->assertEquals(12000, $result['totals']['grand_total']);
        $this->assertEquals($this->ac1->id, $result['rows'][0]['resolved']['accounting_class_id']);
        $this->assertEquals($this->mc1->id, $result['rows'][0]['resolved']['management_class_id']);
        $this->assertEquals($this->cc1->id, $result['rows'][0]['resolved']['cost_center_id']);
        $this->assertEquals($this->store->id, $result['rows'][0]['resolved']['store_id']);
    }

    public function test_rejects_row_missing_required_codes(): void
    {
        $service = app(BudgetImportService::class);

        $path = $this->makeXlsx([
            ['', 'MC-TI', 'CC-001', '', '', '', '', '', 1000, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], [
            'codigo_contabil', 'codigo_gerencial', 'codigo_centro_custo', 'codigo_loja',
            'fornecedor', 'justificativa', 'descricao_conta', 'descricao_classe',
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ]);

        $result = $service->preview($path);

        $this->assertEquals(1, $result['rejected_rows']);
        $this->assertEquals(0, $result['valid_rows']);
        $this->assertStringContainsString('contábil', $result['rows'][0]['errors'][0]);
    }

    public function test_skips_completely_empty_rows(): void
    {
        $service = app(BudgetImportService::class);

        $path = $this->makeXlsx([
            ['', '', '', '', '', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            ['4.2.1.04.00032', 'MC-TI', 'CC-001', '', '', '', '', '', 500, 500, 500, 500, 500, 500, 500, 500, 500, 500, 500, 500],
        ], [
            'codigo_contabil', 'codigo_gerencial', 'codigo_centro_custo', 'codigo_loja',
            'fornecedor', 'justificativa', 'descricao_conta', 'descricao_classe',
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ]);

        $result = $service->preview($path);

        // readFile filtra linhas completamente vazias antes do parse —
        // evita inflar "rejected" em templates com buffer vazio enorme.
        $this->assertEquals(1, $result['total_rows']);
        $this->assertEquals(1, $result['valid_rows']);
        $this->assertEquals(0, $result['rejected_rows']);
    }

    public function test_detects_unresolved_cost_center_with_fuzzy_suggestions(): void
    {
        $service = app(BudgetImportService::class);

        // CC-0O1 (letra O em vez de zero) — 1 letra de distância do CC-001
        $path = $this->makeXlsx([
            ['4.2.1.04.00032', 'MC-TI', 'CC-0O1', '', '', '', '', '', 1000, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], [
            'codigo_contabil', 'codigo_gerencial', 'codigo_centro_custo', 'codigo_loja',
            'fornecedor', 'justificativa', 'descricao_conta', 'descricao_classe',
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ]);

        $result = $service->preview($path);

        $this->assertEquals(1, $result['needs_reconciliation']);
        $this->assertArrayHasKey('cost_center', $result['rows'][0]['unresolved']);
        $this->assertEquals('CC-0O1', $result['rows'][0]['unresolved']['cost_center']);

        // Fuzzy: deve ter sugerido CC-001 (distância 1)
        $ccSuggestions = $result['unresolved_summary']['cost_center'];
        $this->assertNotEmpty($ccSuggestions);
        $this->assertEquals('CC-0O1', $ccSuggestions[0]['code']);
        $this->assertEquals(1, $ccSuggestions[0]['row_count']);
        $suggestion = collect($ccSuggestions[0]['suggestions'])->firstWhere('code', 'CC-001');
        $this->assertNotNull($suggestion);
        $this->assertEquals(1, $suggestion['distance']);
    }

    public function test_fuzzy_respects_distance_threshold(): void
    {
        $service = app(BudgetImportService::class);

        // "TOTALMENTE-DIFERENTE" (nem aproximado) — não deve ter sugestão
        $path = $this->makeXlsx([
            ['4.2.1.04.00032', 'MC-TI', 'TOTALMENTE-DIFERENTE', '', '', '', '', '', 1000, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], [
            'codigo_contabil', 'codigo_gerencial', 'codigo_centro_custo', 'codigo_loja',
            'fornecedor', 'justificativa', 'descricao_conta', 'descricao_classe',
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ]);

        $result = $service->preview($path);

        $this->assertEquals(1, $result['needs_reconciliation']);
        $suggestion = $result['unresolved_summary']['cost_center'][0];
        $this->assertEmpty($suggestion['suggestions']); // Threshold muito distante
    }

    public function test_parses_br_formatted_money_values(): void
    {
        $service = app(BudgetImportService::class);

        // "1.234,56" formato BR → 1234.56
        $path = $this->makeXlsx([
            ['4.2.1.04.00032', 'MC-TI', 'CC-001', '', '', '', '', '', '1.234,56', '500,50', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], [
            'codigo_contabil', 'codigo_gerencial', 'codigo_centro_custo', 'codigo_loja',
            'fornecedor', 'justificativa', 'descricao_conta', 'descricao_classe',
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ]);

        $result = $service->preview($path);

        $this->assertEquals(1, $result['valid_rows']);
        $this->assertEquals(1735.06, $result['rows'][0]['year_total']);
        $this->assertEquals(1234.56, $result['rows'][0]['resolved']['month_01_value']);
        $this->assertEquals(500.50, $result['rows'][0]['resolved']['month_02_value']);
    }

    public function test_resolve_items_applies_mapping(): void
    {
        $service = app(BudgetImportService::class);

        // Linha com CC não resolvido diretamente
        $path = $this->makeXlsx([
            ['4.2.1.04.00032', 'MC-TI', 'CC-DESCONHECIDO', '', '', '', '', '', 1000, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], [
            'codigo_contabil', 'codigo_gerencial', 'codigo_centro_custo', 'codigo_loja',
            'fornecedor', 'justificativa', 'descricao_conta', 'descricao_classe',
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ]);

        // Sem mapping: rejeita como needs_reconciliation → 0 items
        $resultNoMapping = $service->resolveItems($path, []);
        $this->assertEquals(0, $resultNoMapping['stats']['imported']);
        $this->assertEquals(1, $resultNoMapping['stats']['skipped']);

        // Com mapping manual: importa
        $resultWithMapping = $service->resolveItems($path, [
            'cost_center' => ['CC-DESCONHECIDO' => $this->cc2->id],
        ]);
        $this->assertEquals(1, $resultWithMapping['stats']['imported']);
        $this->assertEquals(0, $resultWithMapping['stats']['skipped']);
        $this->assertEquals($this->cc2->id, $resultWithMapping['items'][0]['cost_center_id']);
    }

    public function test_accepts_alternative_header_names(): void
    {
        $service = app(BudgetImportService::class);

        // Headers em estilo diferente ("Contábil", "Gerencial", "Centro de Custo", abrev dos meses)
        $path = $this->makeXlsx([
            ['4.2.1.04.00032', 'MC-TI', 'CC-001', '', 1000, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], [
            'Contábil', 'Gerencial', 'Centro de Custo', 'Fornecedor',
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
        ]);

        $result = $service->preview($path);

        $this->assertEquals(1, $result['valid_rows']);
        $this->assertEquals(1000, $result['rows'][0]['year_total']);
    }

    public function test_groups_unresolved_by_frequency(): void
    {
        $service = app(BudgetImportService::class);

        // 3 linhas com o mesmo CC ausente + 1 linha com outro CC ausente
        $path = $this->makeXlsx([
            ['4.2.1.04.00032', 'MC-TI', 'CC-COMUM', '', '', '', '', '', 100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            ['4.2.1.04.00032', 'MC-TI', 'CC-COMUM', '', '', '', '', '', 200, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            ['4.2.1.04.00032', 'MC-TI', 'CC-COMUM', '', '', '', '', '', 300, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            ['4.2.1.04.00032', 'MC-TI', 'CC-RARO', '', '', '', '', '', 500, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], [
            'codigo_contabil', 'codigo_gerencial', 'codigo_centro_custo', 'codigo_loja',
            'fornecedor', 'justificativa', 'descricao_conta', 'descricao_classe',
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ]);

        $result = $service->preview($path);

        $this->assertEquals(4, $result['needs_reconciliation']);
        $ccList = $result['unresolved_summary']['cost_center'];
        $this->assertCount(2, $ccList);
        // Ordenados por frequência DESC — CC-COMUM (3) vem antes de CC-RARO (1)
        $this->assertEquals('CC-COMUM', $ccList[0]['code']);
        $this->assertEquals(3, $ccList[0]['row_count']);
        $this->assertEquals('CC-RARO', $ccList[1]['code']);
        $this->assertEquals(1, $ccList[1]['row_count']);
    }

    public function test_calculates_by_month_totals(): void
    {
        $service = app(BudgetImportService::class);

        $path = $this->makeXlsx([
            ['4.2.1.04.00032', 'MC-TI', 'CC-001', '', '', '', '', '', 100, 200, 300, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            ['4.2.1.04.00083', 'MC-TI', 'CC-001', '', '', '', '', '', 50, 100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], [
            'codigo_contabil', 'codigo_gerencial', 'codigo_centro_custo', 'codigo_loja',
            'fornecedor', 'justificativa', 'descricao_conta', 'descricao_classe',
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ]);

        $result = $service->preview($path);

        $this->assertEquals(2, $result['valid_rows']);
        $this->assertEquals(150, $result['totals']['by_month'][1]); // 100+50
        $this->assertEquals(300, $result['totals']['by_month'][2]); // 200+100
        $this->assertEquals(300, $result['totals']['by_month'][3]); // 300+0
        $this->assertEquals(750, $result['totals']['grand_total']); // 600+150
    }
}
