<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\CustomerVipTier;
use App\Services\CustomerVipImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CustomerVipImportServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private CustomerVipImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // PhpSpreadsheet (maatwebsite/excel) carrega worksheets em memória
        // — gerar muitos XLSX no setUp pode estourar 128M default.
        @ini_set('memory_limit', '512M');
        $this->setUpTestData();
        $this->service = app(CustomerVipImportService::class);
    }

    private function makeCustomer(string $cpf, string $name = 'CLIENTE'): Customer
    {
        return Customer::create([
            'cigam_code' => '10001-'.substr($cpf, -4),
            'name' => $name,
            'cpf' => $cpf,
            'is_active' => true,
            'synced_at' => now(),
        ]);
    }

    /**
     * Cria UploadedFile XLSX em memória a partir de um array [header, ...rows].
     */
    private function makeXlsx(array $rows): UploadedFile
    {
        $filename = 'vip_import_'.uniqid().'.xlsx';
        Excel::store(
            new class($rows) implements FromArray {
                public function __construct(private array $rows) {}

                public function array(): array
                {
                    return $this->rows;
                }
            },
            $filename,
            'local',
            ExcelType::XLSX,
        );

        $path = storage_path('app/private/'.$filename);
        if (! file_exists($path)) {
            $path = storage_path('app/'.$filename);
        }

        return new UploadedFile($path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    // -------- Comportamento básico --------

    public function test_imports_valid_rows_as_manual_curation(): void
    {
        $c1 = $this->makeCustomer('11111111111', 'JOAO');
        $c2 = $this->makeCustomer('22222222222', 'MARIA');

        $file = $this->makeXlsx([
            ['cpf', 'ano', 'status'],
            ['11111111111', 2026, 'Black'],
            ['22222222222', 2026, 'Gold'],
        ]);

        $summary = $this->service->import($file, $this->adminUser);

        $this->assertSame(2, $summary['imported']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame([], $summary['errors']);

        $tier1 = CustomerVipTier::where('customer_id', $c1->id)->where('year', 2026)->first();
        $this->assertEquals('black', $tier1->final_tier);
        $this->assertEquals(CustomerVipTier::SOURCE_MANUAL, $tier1->source);
        $this->assertEquals($this->adminUser->id, $tier1->curated_by_user_id);
        $this->assertNotNull($tier1->curated_at);

        $tier2 = CustomerVipTier::where('customer_id', $c2->id)->where('year', 2026)->first();
        $this->assertEquals('gold', $tier2->final_tier);
    }

    public function test_updates_existing_tier_preserving_snapshots(): void
    {
        $customer = $this->makeCustomer('33333333333');
        CustomerVipTier::create([
            'customer_id' => $customer->id,
            'year' => 2026,
            'final_tier' => 'gold',
            'total_revenue' => 12500,
            'total_orders' => 7,
            'preferred_store_code' => 'Z441',
            'source' => CustomerVipTier::SOURCE_AUTO,
        ]);

        $file = $this->makeXlsx([
            ['cpf', 'ano', 'status'],
            ['33333333333', 2026, 'Black'],
        ]);

        $summary = $this->service->import($file, $this->adminUser);

        $this->assertSame(0, $summary['imported']);
        $this->assertSame(1, $summary['updated']);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->where('year', 2026)->first();
        $this->assertEquals('black', $tier->final_tier);
        $this->assertEquals(CustomerVipTier::SOURCE_MANUAL, $tier->source);
        // Snapshots preservados
        $this->assertEqualsWithDelta(12500.0, (float) $tier->total_revenue, 0.01);
        $this->assertEquals(7, $tier->total_orders);
        $this->assertEquals('Z441', $tier->preferred_store_code);
    }

    // -------- Validação --------

    public function test_rejects_unknown_cpf_with_error(): void
    {
        $file = $this->makeXlsx([
            ['cpf', 'ano', 'status'],
            ['44444444444', 2026, 'Black'], // não existe em customers
        ]);

        $summary = $this->service->import($file, $this->adminUser);

        $this->assertSame(0, $summary['imported']);
        $this->assertCount(1, $summary['errors']);
        $this->assertStringContainsString('CPF não encontrado', $summary['errors'][0]['message']);
        $this->assertEquals('44444444444', $summary['errors'][0]['cpf']);
    }

    public function test_rejects_invalid_status(): void
    {
        $this->makeCustomer('55555555555');
        $file = $this->makeXlsx([
            ['cpf', 'ano', 'status'],
            ['55555555555', 2026, 'Platinum'],
        ]);

        $summary = $this->service->import($file, $this->adminUser);

        $this->assertSame(0, $summary['imported']);
        $this->assertCount(0, $summary['errors'], 'erro de status é detectado em normalizeRow e não chega ao import');
        // Linha sequer entra na lista deduped
        $this->assertSame(0, CustomerVipTier::count());
    }

    public function test_rejects_invalid_year(): void
    {
        $this->makeCustomer('66666666666');
        $file = $this->makeXlsx([
            ['cpf', 'ano', 'status'],
            ['66666666666', 1999, 'Black'],
        ]);

        $summary = $this->service->import($file, $this->adminUser);
        $this->assertSame(0, CustomerVipTier::count());
    }

    public function test_accepts_status_case_insensitive(): void
    {
        $c1 = $this->makeCustomer('77777777777');
        $c2 = $this->makeCustomer('88888888888');
        $file = $this->makeXlsx([
            ['cpf', 'ano', 'status'],
            ['77777777777', 2026, 'BLACK'],
            ['88888888888', 2026, 'gold'],
        ]);

        $summary = $this->service->import($file, $this->adminUser);
        $this->assertSame(2, $summary['imported']);
        $this->assertEquals('black', CustomerVipTier::where('customer_id', $c1->id)->first()->final_tier);
        $this->assertEquals('gold', CustomerVipTier::where('customer_id', $c2->id)->first()->final_tier);
    }

    // -------- Múltiplos anos --------

    public function test_supports_multiple_years_in_same_file(): void
    {
        $customer = $this->makeCustomer('99999999999');
        $file = $this->makeXlsx([
            ['cpf', 'ano', 'status'],
            ['99999999999', 2025, 'Gold'],
            ['99999999999', 2026, 'Black'],
        ]);

        $summary = $this->service->import($file, $this->adminUser);

        $this->assertSame(2, $summary['imported']);
        $this->assertEquals('gold', CustomerVipTier::where('customer_id', $customer->id)->where('year', 2025)->first()->final_tier);
        $this->assertEquals('black', CustomerVipTier::where('customer_id', $customer->id)->where('year', 2026)->first()->final_tier);
    }

    // -------- Duplicatas --------

    public function test_dedups_within_file_warning_user(): void
    {
        $customer = $this->makeCustomer('10101010100');
        $file = $this->makeXlsx([
            ['cpf', 'ano', 'status'],
            ['10101010100', 2026, 'Gold'],
            ['10101010100', 2026, 'Black'], // mesma chave — sobrescreve
        ]);

        $summary = $this->service->import($file, $this->adminUser);

        $this->assertSame(1, $summary['imported']);
        $this->assertNotEmpty($summary['warnings']);
        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEquals('black', $tier->final_tier, 'última linha vence');
    }

    // -------- Replace year --------

    public function test_replace_year_removes_clients_not_in_file(): void
    {
        $c1 = $this->makeCustomer('20002000200');
        $c2 = $this->makeCustomer('30003000300');
        $c3 = $this->makeCustomer('40004000400');

        // c2 e c3 já estão na lista 2026
        CustomerVipTier::create(['customer_id' => $c2->id, 'year' => 2026, 'final_tier' => 'gold', 'source' => 'auto']);
        CustomerVipTier::create(['customer_id' => $c3->id, 'year' => 2026, 'final_tier' => 'black', 'source' => 'manual', 'curated_at' => now()]);

        // Arquivo só tem c1 e c2 — c3 deve ser removido
        $file = $this->makeXlsx([
            ['cpf', 'ano', 'status'],
            ['20002000200', 2026, 'Black'],
            ['30003000300', 2026, 'Black'],
        ]);

        $summary = $this->service->import($file, $this->adminUser, replaceYear: true);

        $this->assertSame(1, $summary['imported']);
        $this->assertSame(1, $summary['updated']);
        $this->assertSame(1, $summary['total_removed'], 'c3 removido');
        $this->assertContains(2026, $summary['replaced_years']);

        $this->assertNotNull(CustomerVipTier::where('customer_id', $c1->id)->first());
        $this->assertNotNull(CustomerVipTier::where('customer_id', $c2->id)->first());
        $this->assertNull(CustomerVipTier::where('customer_id', $c3->id)->first(), 'c3 deletado pelo replace_year');
    }

    public function test_replace_year_only_affects_years_present_in_file(): void
    {
        $customer = $this->makeCustomer('50005000500');
        // Cliente em 2025 (ano que NÃO está no arquivo) — não deve ser tocado
        CustomerVipTier::create(['customer_id' => $customer->id, 'year' => 2025, 'final_tier' => 'black', 'source' => 'manual']);

        $other = $this->makeCustomer('60006000600');

        $file = $this->makeXlsx([
            ['cpf', 'ano', 'status'],
            ['60006000600', 2026, 'Gold'],
        ]);

        $this->service->import($file, $this->adminUser, replaceYear: true);

        $this->assertNotNull(
            CustomerVipTier::where('customer_id', $customer->id)->where('year', 2025)->first(),
            '2025 não está no arquivo → não é afetado'
        );
    }

    // -------- CPF com pontuação --------

    public function test_strips_punctuation_from_cpf(): void
    {
        $customer = $this->makeCustomer('70007000700');
        $file = $this->makeXlsx([
            ['cpf', 'ano', 'status'],
            ['700.070.007-00', 2026, 'Black'],
        ]);

        $summary = $this->service->import($file, $this->adminUser);
        $this->assertSame(1, $summary['imported']);
        $this->assertNotNull(CustomerVipTier::where('customer_id', $customer->id)->first());
    }
}
