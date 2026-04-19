<?php

namespace Tests\Feature;

use App\Enums\ReturnReasonCategory;
use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use App\Models\ReturnOrder;
use App\Models\ReturnReason;
use App\Models\Store;
use App\Services\ReturnOrderImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReturnOrderImportExportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected ReturnReason $reason;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z441', 'name' => 'E-commerce']);
        $this->reason = ReturnReason::where('code', 'ARREPEND_GERAL')->firstOrFail();
    }

    protected function makeReturn(array $overrides = []): ReturnOrder
    {
        return ReturnOrder::create(array_merge([
            'invoice_number' => 'NF-'.rand(1000, 9999),
            'store_code' => $this->store->code,
            'movement_date' => now()->toDateString(),
            'customer_name' => 'Cliente',
            'sale_total' => 200,
            'type' => ReturnType::TROCA->value,
            'amount_items' => 200,
            'status' => ReturnStatus::PENDING->value,
            'reason_category' => ReturnReasonCategory::ARREPENDIMENTO->value,
            'return_reason_id' => $this->reason->id,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    public function test_export_excel_returns_xlsx(): void
    {
        $this->makeReturn(['invoice_number' => 'EXP-1']);
        $this->makeReturn(['invoice_number' => 'EXP-2']);

        $response = $this->actingAs($this->adminUser)->get(route('returns.export'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_pdf_generates_pdf(): void
    {
        $r = $this->makeReturn(['invoice_number' => 'PDF-1']);

        $response = $this->actingAs($this->adminUser)->get(route('returns.pdf', $r->id));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', substr($content, 0, 5));
    }

    public function test_export_pdf_scoped_by_store(): void
    {
        $store2 = Store::factory()->create(['code' => 'Z424']);
        $r = $this->makeReturn(['store_code' => $store2->code]);

        $this->supportUser->update(['store_id' => $this->store->code]);

        $response = $this->actingAs($this->supportUser)->get(route('returns.pdf', $r->id));

        $response->assertStatus(403);
    }

    protected function createXlsx(array $rows, string $filename = 'import.xlsx'): UploadedFile
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;

        $export = new class($rows) implements FromArray, WithHeadings {
            public function __construct(public array $data) {}
            public function array(): array
            {
                return array_map(fn ($r) => array_values($r), $this->data);
            }
            public function headings(): array
            {
                return array_keys($this->data[0]);
            }
        };

        ExcelFacade::store($export, $filename, 'local');
        $realPath = storage_path('app/private/'.$filename);
        if (! file_exists($realPath)) {
            $realPath = storage_path('app/'.$filename);
        }
        copy($realPath, $path);

        return new UploadedFile($path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_import_preview_lists_valid_rows(): void
    {
        $file = $this->createXlsx([
            [
                'NF' => 'NF-001',
                'Loja' => 'Z441',
                'Data' => '15/04/2026',
                'Cliente' => 'João Silva',
                'Tipo' => 'troca',
                'Categoria' => 'arrependimento',
                'Valor Itens' => '300,00',
                'Status' => 'Completo',
            ],
        ], 'prev-valid.xlsx');

        $service = app(ReturnOrderImportService::class);
        $result = $service->preview($file->getRealPath());

        $this->assertEquals(1, $result['valid_count']);
        $this->assertEquals(0, $result['invalid_count']);
    }

    public function test_import_persists_and_parses_br_decimal(): void
    {
        $file = $this->createXlsx([
            [
                'NF' => 'NF-IMP',
                'Loja' => 'Z441',
                'Data' => '15/04/2026',
                'Cliente' => 'Cliente BR',
                'Tipo' => 'troca',
                'Categoria' => 'arrependimento',
                'Valor Itens' => '1.234,56',
                'Status' => 'Completo',
            ],
        ], 'import-br.xlsx');

        $service = app(ReturnOrderImportService::class);
        $result = $service->import($file->getRealPath(), $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $this->assertDatabaseHas('return_orders', [
            'invoice_number' => 'NF-IMP',
            'store_code' => 'Z441',
            'amount_items' => 1234.56,
        ]);
    }

    public function test_import_tolerates_pt_br_status_values(): void
    {
        $file = $this->createXlsx([
            [
                'NF' => 'NF-STATUS',
                'Loja' => 'Z441',
                'Data' => '15/04/2026',
                'Cliente' => 'Cliente',
                'Tipo' => 'troca',
                'Categoria' => 'defeito',
                'Valor Itens' => '100',
                'Status' => 'APROVADO', // status v1 em PT-BR maiúsculo
            ],
        ], 'import-status.xlsx');

        $service = app(ReturnOrderImportService::class);
        $service->import($file->getRealPath(), $this->adminUser);

        $this->assertDatabaseHas('return_orders', [
            'invoice_number' => 'NF-STATUS',
            'status' => 'approved',
        ]);
    }

    public function test_import_upsert_updates_existing(): void
    {
        $file1 = $this->createXlsx([
            [
                'NF' => 'NF-UP',
                'Loja' => 'Z441',
                'Data' => '15/04/2026',
                'Cliente' => 'Primeiro Nome',
                'Tipo' => 'troca',
                'Categoria' => 'arrependimento',
                'Valor Itens' => '100',
                'Status' => 'Completo',
            ],
        ], 'upsert-1.xlsx');

        $service = app(ReturnOrderImportService::class);
        $r1 = $service->import($file1->getRealPath(), $this->adminUser);
        $this->assertEquals(1, $r1['created']);

        $file2 = $this->createXlsx([
            [
                'NF' => 'NF-UP',
                'Loja' => 'Z441',
                'Data' => '15/04/2026',
                'Cliente' => 'Segundo Nome',
                'Tipo' => 'troca',
                'Categoria' => 'arrependimento',
                'Valor Itens' => '100',
                'Status' => 'Completo',
            ],
        ], 'upsert-2.xlsx');

        $r2 = $service->import($file2->getRealPath(), $this->adminUser);
        $this->assertEquals(0, $r2['created']);
        $this->assertEquals(1, $r2['updated']);

        $this->assertEquals('Segundo Nome', ReturnOrder::where('invoice_number', 'NF-UP')->value('customer_name'));
    }

    public function test_import_skips_invalid_rows(): void
    {
        $file = $this->createXlsx([
            [
                'NF' => 'VALID',
                'Loja' => 'Z441',
                'Data' => '15/04/2026',
                'Cliente' => 'OK',
                'Tipo' => 'troca',
                'Categoria' => 'arrependimento',
                'Valor Itens' => '100',
                'Status' => 'Completo',
            ],
            [
                // Sem cliente → inválida
                'NF' => 'INVALID',
                'Loja' => 'Z441',
                'Data' => '15/04/2026',
                'Cliente' => '',
                'Tipo' => 'troca',
                'Categoria' => 'arrependimento',
                'Valor Itens' => '50',
                'Status' => 'Completo',
            ],
        ], 'mixed.xlsx');

        $service = app(ReturnOrderImportService::class);
        $result = $service->import($file->getRealPath(), $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertDatabaseHas('return_orders', ['invoice_number' => 'VALID']);
        $this->assertDatabaseMissing('return_orders', ['invoice_number' => 'INVALID']);
    }
}
