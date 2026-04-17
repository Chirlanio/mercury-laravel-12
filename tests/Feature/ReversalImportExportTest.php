<?php

namespace Tests\Feature;

use App\Enums\ReversalStatus;
use App\Enums\ReversalType;
use App\Models\Reversal;
use App\Models\ReversalReason;
use App\Models\Store;
use App\Services\ReversalExportService;
use App\Services\ReversalImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReversalImportExportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected ReversalReason $reason;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja']);
        $this->reason = ReversalReason::where('code', 'FURO_ESTOQUE')->firstOrFail();
    }

    protected function makeReversal(array $overrides = []): Reversal
    {
        return Reversal::create(array_merge([
            'invoice_number' => 'NF-'.rand(1000, 9999),
            'store_code' => $this->store->code,
            'movement_date' => now()->toDateString(),
            'customer_name' => 'Cliente',
            'sale_total' => 500,
            'type' => ReversalType::TOTAL->value,
            'amount_original' => 500,
            'amount_reversal' => 500,
            'status' => ReversalStatus::PENDING_REVERSAL->value,
            'reversal_reason_id' => $this->reason->id,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Export
    // ------------------------------------------------------------------

    public function test_export_excel_returns_file_download(): void
    {
        $this->makeReversal(['invoice_number' => 'NF-EXP-1']);
        $this->makeReversal(['invoice_number' => 'NF-EXP-2']);

        $response = $this->actingAs($this->adminUser)->get(route('reversals.export'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_pdf_individual_generates_pdf(): void
    {
        $reversal = $this->makeReversal(['invoice_number' => 'NF-PDF']);

        $response = $this->actingAs($this->adminUser)->get(route('reversals.pdf', $reversal->id));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', substr($content, 0, 5));
    }

    public function test_export_pdf_scoped_by_store(): void
    {
        $store2 = Store::factory()->create(['code' => 'Z425']);
        $reversal = $this->makeReversal(['store_code' => $store2->code]);

        $this->supportUser->update(['store_id' => $this->store->code]);

        $response = $this->actingAs($this->supportUser)->get(route('reversals.pdf', $reversal->id));

        $response->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Import
    // ------------------------------------------------------------------

    /**
     * Cria um XLSX temporário e retorna o caminho.
     */
    protected function createXlsx(array $rows, string $filename = 'import.xlsx'): UploadedFile
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;

        $export = new class($rows) implements FromArray, WithHeadings {
            public function __construct(public array $data) {}
            public function array(): array
            {
                $out = [];
                foreach ($this->data as $row) {
                    $out[] = array_values($row);
                }
                return $out;
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
                'Loja' => 'Z424',
                'Data' => '15/04/2026',
                'Cliente' => 'João Silva',
                'CPF' => '12345678900',
                'Valor Original' => '500,00',
                'Valor Estorno' => '500,00',
                'Tipo' => 'total',
                'Motivo' => 'FURO_ESTOQUE',
            ],
        ], 'prev-valid.xlsx');

        $service = app(ReversalImportService::class);
        $result = $service->preview($file->getRealPath());

        $this->assertEquals(1, $result['valid_count']);
        $this->assertEquals(0, $result['invalid_count']);
        $this->assertCount(1, $result['rows']);
        $this->assertEquals('NF-001', $result['rows'][0]['invoice_number']);
    }

    public function test_import_preview_lists_errors_for_invalid_rows(): void
    {
        $file = $this->createXlsx([
            [
                // Sem loja → erro
                'NF' => 'NF-002',
                'Data' => '15/04/2026',
                'Cliente' => 'João',
                'Valor Original' => '500,00',
                'Valor Estorno' => '500,00',
                'Tipo' => 'total',
                'Motivo' => 'FURO_ESTOQUE',
            ],
        ], 'prev-invalid.xlsx');

        $service = app(ReversalImportService::class);
        $result = $service->preview($file->getRealPath());

        $this->assertEquals(0, $result['valid_count']);
        $this->assertEquals(1, $result['invalid_count']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_import_persists_valid_rows(): void
    {
        $file = $this->createXlsx([
            [
                'NF' => 'NF-IMP-1',
                'Loja' => 'Z424',
                'Data' => '15/04/2026',
                'Cliente' => 'João Silva',
                'Valor Original' => '300,00',
                'Valor Estorno' => '300,00',
                'Tipo' => 'total',
                'Motivo' => 'FURO_ESTOQUE',
                'Status' => 'Estornado',
            ],
        ], 'import-ok.xlsx');

        $service = app(ReversalImportService::class);
        $result = $service->import($file->getRealPath(), $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $this->assertDatabaseHas('reversals', [
            'invoice_number' => 'NF-IMP-1',
            'store_code' => 'Z424',
            'amount_original' => 300,
            'status' => 'reversed',
        ]);
    }

    public function test_import_parses_brazilian_decimal(): void
    {
        $file = $this->createXlsx([
            [
                'NF' => 'NF-BR',
                'Loja' => 'Z424',
                'Data' => '15/04/2026',
                'Cliente' => 'Cliente BR',
                'Valor Original' => '1.234,56',
                'Valor Estorno' => '1.234,56',
                'Tipo' => 'total',
                'Motivo' => 'FURO_ESTOQUE',
            ],
        ], 'import-br-decimal.xlsx');

        $service = app(ReversalImportService::class);
        $service->import($file->getRealPath(), $this->adminUser);

        $this->assertDatabaseHas('reversals', [
            'invoice_number' => 'NF-BR',
            'amount_original' => 1234.56,
        ]);
    }

    public function test_import_upsert_updates_existing(): void
    {
        $file = $this->createXlsx([
            [
                'NF' => 'NF-UP',
                'Loja' => 'Z424',
                'Data' => '15/04/2026',
                'Cliente' => 'Primeiro Nome',
                'Valor Original' => '200,00',
                'Valor Estorno' => '200,00',
                'Tipo' => 'total',
                'Motivo' => 'FURO_ESTOQUE',
            ],
        ], 'import-upsert-1.xlsx');

        $service = app(ReversalImportService::class);
        $result1 = $service->import($file->getRealPath(), $this->adminUser);
        $this->assertEquals(1, $result1['created']);

        // Segundo import com o MESMO NF/loja/valor original mas cliente diferente
        $file2 = $this->createXlsx([
            [
                'NF' => 'NF-UP',
                'Loja' => 'Z424',
                'Data' => '15/04/2026',
                'Cliente' => 'Segundo Nome',
                'Valor Original' => '200,00',
                'Valor Estorno' => '200,00',
                'Tipo' => 'total',
                'Motivo' => 'FURO_ESTOQUE',
            ],
        ], 'import-upsert-2.xlsx');

        $result2 = $service->import($file2->getRealPath(), $this->adminUser);
        $this->assertEquals(0, $result2['created']);
        $this->assertEquals(1, $result2['updated']);

        $this->assertEquals(1, Reversal::where('invoice_number', 'NF-UP')->count());
        $this->assertEquals('Segundo Nome', Reversal::where('invoice_number', 'NF-UP')->value('customer_name'));
    }

    public function test_import_skips_invalid_rows_but_commits_valid_ones(): void
    {
        $file = $this->createXlsx([
            [
                'NF' => 'VALID',
                'Loja' => 'Z424',
                'Data' => '15/04/2026',
                'Cliente' => 'OK',
                'Valor Original' => '100,00',
                'Valor Estorno' => '100,00',
                'Tipo' => 'total',
                'Motivo' => 'FURO_ESTOQUE',
            ],
            [
                // Sem loja → erro
                'NF' => 'INVALID',
                'Loja' => '',
                'Data' => '15/04/2026',
                'Cliente' => 'Sem Loja',
                'Valor Original' => '50,00',
                'Valor Estorno' => '50,00',
                'Tipo' => 'total',
                'Motivo' => 'FURO_ESTOQUE',
            ],
        ], 'import-mixed.xlsx');

        $service = app(ReversalImportService::class);
        $result = $service->import($file->getRealPath(), $this->adminUser);

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertDatabaseHas('reversals', ['invoice_number' => 'VALID']);
        $this->assertDatabaseMissing('reversals', ['invoice_number' => 'INVALID']);
    }
}
