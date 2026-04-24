<?php

namespace Tests\Feature\Consignments;

use App\Enums\ConsignmentStatus;
use App\Enums\ConsignmentType;
use App\Models\Consignment;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Import de consignações (Fase 6 — migração v1 → v2).
 *
 * Cobertura: preview + import, agrupamento por (doc+loja+NF), resolução
 * de produto via referência+tamanho, órfãos, upsert em re-import.
 */
class ConsignmentImportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Product $product;

    protected ProductVariant $variant;

    protected int $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        app(\App\Services\CentralRoleResolver::class)->clearCache();

        $this->store = Store::factory()->create(['code' => 'Z421']);
        $this->employeeId = $this->createTestEmployee([
            'store_id' => 'Z421',
            'name' => 'João Consultor',
        ]);

        $this->product = Product::create([
            'reference' => 'IMP-001',
            'description' => 'Sandália teste',
            'sale_price' => 150.00,
            'is_active' => true,
        ]);

        ProductSize::firstOrCreate(
            ['cigam_code' => 'U36'],
            ['name' => '36', 'is_active' => true],
        );

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'barcode' => 'IMP-001U36',
            'aux_reference' => '7891234567890',
            'size_cigam_code' => 'U36',
            'is_active' => true,
        ]);

        config(['queue.default' => 'sync']);
    }

    protected function makeXlsxFile(array $rows): UploadedFile
    {
        Storage::fake('local');
        $path = storage_path('app/test-consignment-import-'.uniqid().'.xlsx');

        $export = new class($rows) implements FromArray
        {
            public function __construct(public $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        };

        Excel::store($export, basename($path), 'local');
        $storedPath = Storage::disk('local')->path(basename($path));

        return new UploadedFile($storedPath, basename($path), null, null, true);
    }

    // ==================================================================
    // Preview
    // ==================================================================

    public function test_import_preview_groups_rows_by_invoice_and_counts_valid(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'nome', 'loja', 'nf_saida', 'data_nf', 'consultor', 'referencia', 'tamanho', 'quantidade', 'valor_unit'],
            ['Cliente', '123.456.789-09', 'Maria', 'Z421', '7001', '2026-01-15', 'João Consultor', 'IMP-001', 'U36', 1, 150.00],
            ['Cliente', '123.456.789-09', 'Maria', 'Z421', '7001', '2026-01-15', 'João Consultor', 'IMP-001', 'U36', 1, 150.00],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('consignments.import.preview'), ['file' => $file]);

        $response->assertOk();
        $json = $response->json();

        // Duas linhas viram UM grupo (mesma chave doc+loja+NF)
        $this->assertSame(1, $json['valid_groups']);
        $this->assertSame(0, $json['invalid_groups']);
        $this->assertCount(0, $json['orphans']);
    }

    public function test_import_preview_flags_orphan_for_unknown_reference(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'nome', 'loja', 'nf_saida', 'data_nf', 'consultor', 'referencia', 'tamanho', 'quantidade', 'valor_unit'],
            ['Cliente', '123.456.789-09', 'Maria', 'Z421', '7002', '2026-01-15', 'João Consultor', 'NAO-EXISTE', 'U36', 1, 150.00],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('consignments.import.preview'), ['file' => $file]);

        $response->assertOk();
        $json = $response->json();

        $this->assertCount(1, $json['orphans']);
        $this->assertSame('NAO-EXISTE', $json['orphans'][0]['reference']);
        // Grupo inválido porque todos os itens viraram órfãos
        $this->assertSame(1, $json['invalid_groups']);
    }

    public function test_import_preview_rejects_invalid_document(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'nome', 'loja', 'nf_saida', 'data_nf', 'consultor', 'referencia', 'tamanho', 'quantidade', 'valor_unit'],
            ['Cliente', '123', 'Maria', 'Z421', '7003', '2026-01-15', 'João Consultor', 'IMP-001', 'U36', 1, 150.00],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('consignments.import.preview'), ['file' => $file]);

        $response->assertOk();
        $json = $response->json();

        $this->assertSame(1, $json['invalid_groups']);
        $this->assertNotEmpty($json['errors']);
    }

    public function test_import_preview_rejects_unknown_store(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'nome', 'loja', 'nf_saida', 'data_nf', 'consultor', 'referencia', 'tamanho', 'quantidade', 'valor_unit'],
            ['Cliente', '123.456.789-09', 'Maria', 'ZZZ', '7004', '2026-01-15', 'João Consultor', 'IMP-001', 'U36', 1, 150.00],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('consignments.import.preview'), ['file' => $file]);

        $response->assertOk();
        $json = $response->json();

        $this->assertSame(1, $json['invalid_groups']);
    }

    // ==================================================================
    // Import (store)
    // ==================================================================

    public function test_import_creates_consignment_with_item(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'nome', 'loja', 'nf_saida', 'data_nf', 'consultor', 'referencia', 'tamanho', 'quantidade', 'valor_unit', 'status'],
            ['Cliente', '123.456.789-09', 'Maria Silva', 'Z421', '8001', '2026-01-15', 'João Consultor', 'IMP-001', 'U36', 2, 150.00, 'pendente'],
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('consignments.import.store'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('consignments', [
            'recipient_document_clean' => '12345678909',
            'outbound_invoice_number' => '8001',
            'outbound_store_code' => 'Z421',
            'status' => ConsignmentStatus::PENDING->value,
            'type' => ConsignmentType::CLIENTE->value,
        ]);

        $consignment = Consignment::where('outbound_invoice_number', '8001')->first();
        $this->assertNotNull($consignment);
        $this->assertCount(1, $consignment->items);
        $this->assertEquals($this->product->id, $consignment->items->first()->product_id);
        $this->assertEquals(2, $consignment->items->first()->quantity);
    }

    public function test_import_is_idempotent_on_same_invoice(): void
    {
        $rows = [
            ['tipo', 'cpf', 'nome', 'loja', 'nf_saida', 'data_nf', 'consultor', 'referencia', 'tamanho', 'quantidade', 'valor_unit'],
            ['Cliente', '123.456.789-09', 'Maria', 'Z421', '8002', '2026-01-15', 'João Consultor', 'IMP-001', 'U36', 1, 150.00],
        ];

        $this->actingAs($this->adminUser)
            ->post(route('consignments.import.store'), ['file' => $this->makeXlsxFile($rows)])
            ->assertRedirect();

        $this->actingAs($this->adminUser)
            ->post(route('consignments.import.store'), ['file' => $this->makeXlsxFile($rows)])
            ->assertRedirect();

        // Apenas um registro — re-import atualiza em vez de criar duplicata
        $this->assertSame(
            1,
            Consignment::where('outbound_invoice_number', '8002')
                ->where('outbound_store_code', 'Z421')
                ->count(),
        );
    }

    public function test_import_skips_orphan_items_but_persists_valid_ones(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'nome', 'loja', 'nf_saida', 'data_nf', 'consultor', 'referencia', 'tamanho', 'quantidade', 'valor_unit'],
            ['Cliente', '123.456.789-09', 'Maria', 'Z421', '8003', '2026-01-15', 'João Consultor', 'IMP-001', 'U36', 1, 150.00],
            ['Cliente', '123.456.789-09', 'Maria', 'Z421', '8003', '2026-01-15', 'João Consultor', 'NAO-EXISTE', 'U36', 1, 150.00],
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('consignments.import.store'), ['file' => $file])
            ->assertRedirect();

        $consignment = Consignment::where('outbound_invoice_number', '8003')->first();
        $this->assertNotNull($consignment);
        $this->assertCount(1, $consignment->items); // Só o válido
    }

    public function test_import_requires_permission(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'nome', 'loja', 'nf_saida', 'data_nf', 'consultor', 'referencia', 'tamanho', 'quantidade', 'valor_unit'],
            ['Cliente', '123.456.789-09', 'Maria', 'Z421', '8004', '2026-01-15', 'João Consultor', 'IMP-001', 'U36', 1, 150.00],
        ]);

        // Usuário regular não tem IMPORT_CONSIGNMENTS → 403
        $this->actingAs($this->regularUser)
            ->post(route('consignments.import.store'), ['file' => $file])
            ->assertForbidden();
    }

    public function test_import_derives_completed_state_from_status_column(): void
    {
        $file = $this->makeXlsxFile([
            ['tipo', 'cpf', 'nome', 'loja', 'nf_saida', 'data_nf', 'consultor', 'referencia', 'tamanho', 'quantidade', 'valor_unit', 'status'],
            ['Cliente', '123.456.789-09', 'Maria', 'Z421', '8005', '2026-01-15', 'João Consultor', 'IMP-001', 'U36', 1, 150.00, 'finalizada'],
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('consignments.import.store'), ['file' => $file])
            ->assertRedirect();

        $consignment = Consignment::where('outbound_invoice_number', '8005')->first();
        $this->assertNotNull($consignment);
        $this->assertEquals(ConsignmentStatus::COMPLETED, $consignment->status);
        $this->assertNotNull($consignment->completed_at);
    }

    public function test_import_page_rendered_for_permitted_user(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('consignments.import'))
            ->assertInertia(fn ($page) => $page->component('Consignments/Import'));
    }
}
