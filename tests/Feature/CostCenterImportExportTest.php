<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use App\Services\CostCenterImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CostCenterImportExportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    protected function makeExcel(array $rows, array $headings = ['codigo', 'nome', 'descricao', 'codigo_pai', 'responsavel', 'ativo']): string
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

        $path = tempnam(sys_get_temp_dir(), 'cc-test').'.xlsx';
        Excel::store($export, basename($path), 'local');

        $stored = storage_path('app/private/'.basename($path));
        if (! file_exists($stored)) {
            $stored = storage_path('app/'.basename($path));
        }

        return $stored;
    }

    public function test_export_returns_xlsx_download(): void
    {
        CostCenter::create([
            'code' => 'EXP-1',
            'name' => 'Para Exportar',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('cost-centers.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
    }

    public function test_preview_returns_valid_and_invalid_counts(): void
    {
        $service = app(CostCenterImportService::class);

        $path = $this->makeExcel([
            ['CC-100', 'Válido 1', 'Desc', '', '', 'sim'],
            ['CC-101', 'Válido 2', 'Outra', '', '', 'sim'],
            ['', 'Sem código', '', '', '', 'sim'],            // inválido: sem código
            ['CC-102', '', '', '', '', 'sim'],                 // inválido: sem nome
            ['CC-100', 'Duplicado na planilha', '', '', '', 'sim'], // inválido: duplicado
        ]);

        $result = $service->preview($path);

        $this->assertEquals(2, $result['valid_count']);
        $this->assertEquals(3, $result['invalid_count']);
        $this->assertCount(2, $result['rows']);
        $this->assertCount(3, $result['errors']);
    }

    public function test_import_creates_and_updates_by_code(): void
    {
        // Existing — será atualizado
        CostCenter::create([
            'code' => 'CC-UPD',
            'name' => 'Nome Antigo',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $service = app(CostCenterImportService::class);

        $path = $this->makeExcel([
            ['CC-NEW', 'Novo CC', 'Novo', '', '', 'sim'],
            ['CC-UPD', 'Nome Atualizado', '', '', '', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['updated']);
        $this->assertEquals(0, $result['skipped']);

        $this->assertDatabaseHas('cost_centers', [
            'code' => 'CC-NEW',
            'name' => 'Novo CC',
        ]);
        $this->assertDatabaseHas('cost_centers', [
            'code' => 'CC-UPD',
            'name' => 'Nome Atualizado',
        ]);
    }

    public function test_import_resolves_parent_by_code(): void
    {
        CostCenter::create([
            'code' => 'PARENT',
            'name' => 'Pai',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $service = app(CostCenterImportService::class);

        $path = $this->makeExcel([
            ['CHILD-1', 'Filho', '', 'PARENT', '', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $child = CostCenter::where('code', 'CHILD-1')->first();
        $this->assertNotNull($child->parent_id);
        $this->assertEquals('PARENT', $child->parent->code);
    }

    public function test_import_skips_rows_without_code(): void
    {
        $service = app(CostCenterImportService::class);

        $path = $this->makeExcel([
            ['', 'Sem código', '', '', '', 'sim'],
            ['OK-1', 'Válido', '', '', '', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_import_rejects_file_wrong_extension(): void
    {
        $fake = UploadedFile::fake()->create('dados.txt', 100);

        $response = $this->actingAs($this->adminUser)
            ->post(route('cost-centers.import.store'), ['file' => $fake]);

        $response->assertSessionHasErrors('file');
    }

    public function test_import_endpoint_happy_path(): void
    {
        $path = $this->makeExcel([
            ['HTTP-1', 'Via HTTP', '', '', '', 'sim'],
        ]);

        $file = new UploadedFile($path, basename($path), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->adminUser)
            ->post(route('cost-centers.import.store'), ['file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('cost_centers', ['code' => 'HTTP-1']);
    }
}
