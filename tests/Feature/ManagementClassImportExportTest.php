<?php

namespace Tests\Feature;

use App\Models\AccountingClass;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Services\ManagementClassImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ManagementClassImportExportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AccountingClass $acLeaf;

    protected AccountingClass $acGroup;

    protected CostCenter $cc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->acGroup = AccountingClass::where('code', '4.2.1.04')->firstOrFail(); // Despesas Administrativas (sintético)
        $this->acLeaf = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail(); // Telefonia (analítica)

        $this->cc = CostCenter::create([
            'code' => 'CC-MC-IMP',
            'name' => 'CC para MC Import',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    protected function makeExcel(array $rows, array $headings = ['codigo', 'nome', 'descricao', 'codigo_pai', 'codigo_contabil', 'codigo_centro_custo', 'aceita_lancamento', 'ativo']): string
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

        $path = tempnam(sys_get_temp_dir(), 'mc-test').'.xlsx';
        Excel::store($export, basename($path), 'local');

        $stored = storage_path('app/private/'.basename($path));
        if (! file_exists($stored)) {
            $stored = storage_path('app/'.basename($path));
        }

        return $stored;
    }

    public function test_export_returns_xlsx(): void
    {
        ManagementClass::create([
            'code' => 'EXP-MC',
            'name' => 'Para exportar',
            'accepts_entries' => true,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('management-classes.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
    }

    public function test_preview_counts_valid_and_invalid(): void
    {
        $service = app(ManagementClassImportService::class);

        $path = $this->makeExcel([
            ['MC-100', 'OK 1', '', '', '', '', 'sim', 'sim'],
            ['MC-101', 'OK 2', '', '', '', '', 'sim', 'sim'],
            ['', 'Sem código', '', '', '', '', 'sim', 'sim'],
            ['MC-102', '', '', '', '', '', 'sim', 'sim'],
            ['MC-100', 'Duplicado', '', '', '', '', 'sim', 'sim'],
        ]);

        $result = $service->preview($path);

        $this->assertEquals(2, $result['valid_count']);
        $this->assertEquals(3, $result['invalid_count']);
    }

    public function test_import_creates_and_updates_by_code(): void
    {
        ManagementClass::create([
            'code' => 'MC-UPD',
            'name' => 'Antigo',
            'accepts_entries' => true,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $service = app(ManagementClassImportService::class);

        $path = $this->makeExcel([
            ['MC-NEW', 'Novo', '', '', '', '', 'sim', 'sim'],
            ['MC-UPD', 'Atualizado', '', '', '', '', 'sim', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['updated']);
        $this->assertDatabaseHas('management_classes', ['code' => 'MC-NEW']);
        $this->assertDatabaseHas('management_classes', [
            'code' => 'MC-UPD',
            'name' => 'Atualizado',
        ]);
    }

    public function test_import_resolves_accounting_class_link(): void
    {
        $service = app(ManagementClassImportService::class);

        $path = $this->makeExcel([
            ['MC-WITH-AC', 'Com vínculo', '', '', $this->acLeaf->code, '', 'sim', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $mc = ManagementClass::where('code', 'MC-WITH-AC')->first();
        $this->assertEquals($this->acLeaf->id, $mc->accounting_class_id);
    }

    public function test_import_rejects_accounting_link_to_synthetic_group(): void
    {
        $service = app(ManagementClassImportService::class);

        $path = $this->makeExcel([
            ['MC-BAD-AC', 'Tentativa agrupador', '', '', $this->acGroup->code, '', 'sim', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_import_resolves_cost_center_link(): void
    {
        $service = app(ManagementClassImportService::class);

        $path = $this->makeExcel([
            ['MC-WITH-CC', 'Com CC', '', '', '', $this->cc->code, 'sim', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $mc = ManagementClass::where('code', 'MC-WITH-CC')->first();
        $this->assertEquals($this->cc->id, $mc->cost_center_id);
    }

    public function test_import_resolves_parent_by_code(): void
    {
        ManagementClass::create([
            'code' => 'MC-PARENT',
            'name' => 'Pai',
            'accepts_entries' => false,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $service = app(ManagementClassImportService::class);

        $path = $this->makeExcel([
            ['MC-CHILD', 'Filha', '', 'MC-PARENT', '', '', 'sim', 'sim'],
        ]);

        $result = $service->import($path, $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $child = ManagementClass::where('code', 'MC-CHILD')->first();
        $this->assertNotNull($child->parent_id);
    }

    public function test_import_rejects_file_wrong_extension(): void
    {
        $fake = UploadedFile::fake()->create('dados.txt', 100);

        $response = $this->actingAs($this->adminUser)
            ->post(route('management-classes.import.store'), ['file' => $fake]);

        $response->assertSessionHasErrors('file');
    }
}
