<?php

namespace Tests\Feature;

use App\Models\AccountingClass;
use App\Models\BudgetUpload;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BudgetImportEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AccountingClass $ac;

    protected ManagementClass $mc;

    protected ManagementClass $areaDepartment;

    protected CostCenter $cc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        Storage::fake('local');

        $this->ac = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail(); // Telefonia

        $this->cc = CostCenter::create([
            'code' => 'CC-IMP',
            'name' => 'Admin Import',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        // Fase 5: MC precisa de parent_id apontando para um departamento
        // para passar pelo validateAreaCoherence.
        $this->areaDepartment = ManagementClass::where('code', '8.1.01')->firstOrFail();

        $this->mc = ManagementClass::create([
            'code' => 'MC-IMP',
            'name' => 'Gerencial Import',
            'accepts_entries' => true,
            'accounting_class_id' => $this->ac->id,
            'parent_id' => $this->areaDepartment->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    protected function makeXlsxUpload(array $rows, array $headings, string $name = 'orc.xlsx'): UploadedFile
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

        // Gera em memória e escreve em temp dir (bypassa Storage::fake)
        $raw = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'xlsx_').'.xlsx';
        file_put_contents($path, $raw);

        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    protected function headers(): array
    {
        return [
            'codigo_contabil', 'codigo_gerencial', 'codigo_centro_custo', 'codigo_loja',
            'fornecedor', 'justificativa', 'descricao_conta', 'descricao_classe',
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ];
    }

    public function test_preview_returns_diagnostic(): void
    {
        $file = $this->makeXlsxUpload([
            ['4.2.1.04.00032', 'MC-IMP', 'CC-IMP', '', '', '', '', '', 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000],
            ['4.2.1.04.00032', 'MC-IMP', 'CC-INEXISTENTE', '', '', '', '', '', 500, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], $this->headers());

        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.preview'), ['file' => $file]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_rows', 'valid_rows', 'needs_reconciliation', 'rejected_rows',
            'rows', 'unresolved_summary', 'totals',
        ]);
        $response->assertJsonPath('valid_rows', 1);
        $response->assertJsonPath('needs_reconciliation', 1);
    }

    public function test_preview_requires_upload_permission(): void
    {
        $file = $this->makeXlsxUpload([
            ['4.2.1.04.00032', 'MC-IMP', 'CC-IMP', '', '', '', '', '', 100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], $this->headers());

        $response = $this->actingAs($this->regularUser)
            ->post(route('budgets.preview'), ['file' => $file]);

        $response->assertStatus(403);
    }

    public function test_preview_rejects_invalid_file_extension(): void
    {
        $fake = UploadedFile::fake()->create('dados.txt', 50);

        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.preview'), ['file' => $fake]);

        $response->assertSessionHasErrors('file');
    }

    public function test_import_creates_budget_from_xlsx(): void
    {
        $file = $this->makeXlsxUpload([
            ['4.2.1.04.00032', 'MC-IMP', 'CC-IMP', '', 'Forn X', '', '', '', 1000, 1000, 1000, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], $this->headers());

        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.import'), [
                'file' => $file,
                'year' => 2026,
                'scope_label' => 'ImportTest',
                'area_department_id' => $this->areaDepartment->id,
                'upload_type' => 'novo',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('budget_uploads', [
            'year' => 2026,
            'scope_label' => 'ImportTest',
            'version_label' => '1.0',
            'items_count' => 1,
        ]);
    }

    public function test_import_applies_mapping_for_unresolved_codes(): void
    {
        // Linha com CC "CC-ERRADO" não existente — deve resolver via mapping
        $file = $this->makeXlsxUpload([
            ['4.2.1.04.00032', 'MC-IMP', 'CC-ERRADO', '', '', '', '', '', 500, 500, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], $this->headers());

        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.import'), [
                'file' => $file,
                'year' => 2026,
                'scope_label' => 'WithMapping',
                'area_department_id' => $this->areaDepartment->id,
                'upload_type' => 'novo',
                'mapping' => [
                    'cost_center' => [
                        'CC-ERRADO' => $this->cc->id,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $upload = BudgetUpload::where('scope_label', 'WithMapping')->first();
        $this->assertNotNull($upload);
        $this->assertEquals(1, $upload->items_count);

        // Item foi criado com o CC do mapping
        $this->assertDatabaseHas('budget_items', [
            'budget_upload_id' => $upload->id,
            'cost_center_id' => $this->cc->id,
        ]);
    }

    public function test_import_fails_when_no_valid_rows_after_mapping(): void
    {
        // Linha com códigos todos ausentes e sem mapping — nenhuma linha válida
        $file = $this->makeXlsxUpload([
            ['ERR-X', 'ERR-Y', 'ERR-Z', '', '', '', '', '', 100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], $this->headers());

        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.import'), [
                'file' => $file,
                'year' => 2026,
                'scope_label' => 'NoValid',
                'area_department_id' => $this->areaDepartment->id,
                'upload_type' => 'novo',
            ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('budget_uploads', ['scope_label' => 'NoValid']);
    }

    public function test_import_validates_mapping_ids_exist(): void
    {
        $file = $this->makeXlsxUpload([
            ['4.2.1.04.00032', 'MC-IMP', 'CC-IMP', '', '', '', '', '', 100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], $this->headers());

        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.import'), [
                'file' => $file,
                'year' => 2026,
                'scope_label' => 'BadMap',
                'area_department_id' => $this->areaDepartment->id,
                'upload_type' => 'novo',
                'mapping' => [
                    'cost_center' => [
                        'CC-PLANILHA' => 999999, // ID inexistente
                    ],
                ],
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_import_validates_required_header_fields(): void
    {
        $file = $this->makeXlsxUpload([
            ['4.2.1.04.00032', 'MC-IMP', 'CC-IMP', '', '', '', '', '', 100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        ], $this->headers());

        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.import'), [
                'file' => $file,
                // Sem year, scope_label, upload_type
            ]);

        $response->assertSessionHasErrors(['year', 'scope_label', 'upload_type']);
    }

    public function test_template_download_returns_xlsx(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('budgets.template'));

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
    }
}
