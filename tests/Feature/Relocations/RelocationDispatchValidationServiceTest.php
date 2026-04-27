<?php

namespace Tests\Feature\Relocations;

use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\RelocationItem;
use App\Models\RelocationType;
use App\Models\Store;
use App\Services\RelocationDispatchValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class RelocationDispatchValidationServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $origin;
    protected Store $destination;
    protected RelocationType $type;
    protected RelocationDispatchValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->origin = Store::factory()->create(['code' => 'Z424', 'network_id' => 1]);
        $this->destination = Store::factory()->create(['code' => 'Z423', 'network_id' => 1]);
        $this->type = RelocationType::firstOrCreate(
            ['code' => 'PLANEJAMENTO'],
            ['name' => 'Planejamento', 'is_active' => true, 'sort_order' => 10]
        );
        $this->service = app(RelocationDispatchValidationService::class);
    }

    public function test_returns_nf_not_found_when_movements_empty(): void
    {
        $reloc = $this->createRelocationInSeparation([
            ['barcode' => 'EAN-1', 'qty_separated' => 3],
        ]);

        $result = $this->service->validate($reloc, 'NF-9999', '2026-04-27');

        $this->assertFalse($result['nf_found']);
        $this->assertFalse($result['has_discrepancies']);
        $this->assertEmpty($result['matched']);
        $this->assertEmpty($result['missing']);
    }

    public function test_matches_when_invoice_items_equal_separated(): void
    {
        $reloc = $this->createRelocationInSeparation([
            ['barcode' => 'EAN-A', 'qty_separated' => 3],
            ['barcode' => 'EAN-B', 'qty_separated' => 1],
        ]);

        $this->insertMovement('Z424', 'NF-001', '2026-04-27', 'EAN-A', 3);
        $this->insertMovement('Z424', 'NF-001', '2026-04-27', 'EAN-B', 1);

        $result = $this->service->validate($reloc, 'NF-001', '2026-04-27');

        $this->assertTrue($result['nf_found']);
        $this->assertFalse($result['has_discrepancies']);
        $this->assertCount(2, $result['matched']);
        $this->assertEmpty($result['missing']);
        $this->assertEmpty($result['extra']);
        $this->assertEmpty($result['divergent']);
        $this->assertSame(4, $result['total_items_in_invoice']);
        $this->assertSame(4, $result['total_items_separated']);
    }

    public function test_detects_missing_items_in_invoice(): void
    {
        $reloc = $this->createRelocationInSeparation([
            ['barcode' => 'EAN-A', 'qty_separated' => 3],
            ['barcode' => 'EAN-B', 'qty_separated' => 2],  // separado mas NÃO na NF
        ]);

        $this->insertMovement('Z424', 'NF-002', '2026-04-27', 'EAN-A', 3);

        $result = $this->service->validate($reloc, 'NF-002', '2026-04-27');

        $this->assertTrue($result['nf_found']);
        $this->assertTrue($result['has_discrepancies']);
        $this->assertCount(1, $result['matched']);
        $this->assertCount(1, $result['missing']);
        $this->assertSame('EAN-B', $result['missing'][0]['barcode']);
        $this->assertSame(2, $result['missing'][0]['qty_separated']);
        $this->assertSame(0, $result['missing'][0]['qty_in_invoice']);
    }

    public function test_detects_extra_items_in_invoice(): void
    {
        $reloc = $this->createRelocationInSeparation([
            ['barcode' => 'EAN-A', 'qty_separated' => 2],
        ]);

        $this->insertMovement('Z424', 'NF-003', '2026-04-27', 'EAN-A', 2);
        $this->insertMovement('Z424', 'NF-003', '2026-04-27', 'EAN-X', 5);  // não solicitado

        $result = $this->service->validate($reloc, 'NF-003', '2026-04-27');

        $this->assertTrue($result['has_discrepancies']);
        $this->assertCount(1, $result['matched']);
        $this->assertCount(1, $result['extra']);
        $this->assertSame('EAN-X', $result['extra'][0]['barcode']);
        $this->assertSame(5, $result['extra'][0]['qty_in_invoice']);
    }

    public function test_extra_items_include_product_metadata_from_catalog(): void
    {
        // Cria produto + variant pra que o lookup encontre
        $product = \App\Models\Product::create([
            'reference' => 'REF-EXTRA',
            'description' => 'BOLSA TESTE EXTRA',
        ]);
        $size = \App\Models\ProductSize::firstOrCreate(
            ['cigam_code' => 'U35'],
            ['name' => '35'],
        );
        \App\Models\ProductVariant::create([
            'product_id' => $product->id,
            'barcode' => 'EAN-EXTRA',
            'size_cigam_code' => $size->cigam_code,
            'is_active' => true,
        ]);

        $reloc = $this->createRelocationInSeparation([
            ['barcode' => 'EAN-A', 'qty_separated' => 1],
        ]);

        $this->insertMovement('Z424', 'NF-EXTRA-META', '2026-04-27', 'EAN-A', 1);
        $this->insertMovement('Z424', 'NF-EXTRA-META', '2026-04-27', 'EAN-EXTRA', 2);

        $result = $this->service->validate($reloc, 'NF-EXTRA-META', '2026-04-27');

        $this->assertCount(1, $result['extra']);
        $extraItem = $result['extra'][0];
        $this->assertSame('EAN-EXTRA', $extraItem['barcode']);
        $this->assertSame(2, $extraItem['qty_in_invoice']);
        $this->assertSame('BOLSA TESTE EXTRA', $extraItem['product_name']);
        $this->assertSame('REF-EXTRA', $extraItem['product_reference']);
        $this->assertSame('35', $extraItem['size']);
    }

    public function test_extra_items_fallback_when_barcode_not_in_catalog(): void
    {
        $reloc = $this->createRelocationInSeparation([
            ['barcode' => 'EAN-A', 'qty_separated' => 1],
        ]);

        $this->insertMovement('Z424', 'NF-NO-CATALOG', '2026-04-27', 'EAN-A', 1);
        $this->insertMovement('Z424', 'NF-NO-CATALOG', '2026-04-27', 'EAN-UNKNOWN', 3);

        $result = $this->service->validate($reloc, 'NF-NO-CATALOG', '2026-04-27');

        $this->assertCount(1, $result['extra']);
        $this->assertSame('EAN-UNKNOWN', $result['extra'][0]['barcode']);
        $this->assertNull($result['extra'][0]['product_name']);
        $this->assertNull($result['extra'][0]['product_reference']);
    }

    public function test_detects_divergent_quantity(): void
    {
        $reloc = $this->createRelocationInSeparation([
            ['barcode' => 'EAN-A', 'qty_separated' => 5],
        ]);

        $this->insertMovement('Z424', 'NF-004', '2026-04-27', 'EAN-A', 3);  // qty diferente

        $result = $this->service->validate($reloc, 'NF-004', '2026-04-27');

        $this->assertTrue($result['has_discrepancies']);
        $this->assertCount(1, $result['divergent']);
        $this->assertSame(5, $result['divergent'][0]['qty_separated']);
        $this->assertSame(3, $result['divergent'][0]['qty_in_invoice']);
    }

    public function test_filters_only_movement_code_5_and_entry_exit_S(): void
    {
        $reloc = $this->createRelocationInSeparation([
            ['barcode' => 'EAN-A', 'qty_separated' => 3],
        ]);

        // Linha que NÃO conta (code errado)
        DB::table('movements')->insert([
            'movement_code' => 2,  // venda, não transferência
            'entry_exit' => 'S',
            'store_code' => 'Z424',
            'invoice_number' => 'NF-005',
            'movement_date' => '2026-04-27',
            'barcode' => 'EAN-A',
            'quantity' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Linha que NÃO conta (entry_exit='E' = entrada, não saída)
        DB::table('movements')->insert([
            'movement_code' => 5,
            'entry_exit' => 'E',
            'store_code' => 'Z424',
            'invoice_number' => 'NF-005',
            'movement_date' => '2026-04-27',
            'barcode' => 'EAN-A',
            'quantity' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->validate($reloc, 'NF-005', '2026-04-27');

        $this->assertFalse($result['nf_found']);
    }

    public function test_filters_by_origin_store_code(): void
    {
        $reloc = $this->createRelocationInSeparation([
            ['barcode' => 'EAN-A', 'qty_separated' => 3],
        ]);

        // Mesma NF mas em outra loja — não conta
        $this->insertMovement('Z999', 'NF-006', '2026-04-27', 'EAN-A', 3);

        $result = $this->service->validate($reloc, 'NF-006', '2026-04-27');

        $this->assertFalse($result['nf_found']);
    }

    public function test_aggregates_multiple_lines_per_barcode(): void
    {
        $reloc = $this->createRelocationInSeparation([
            ['barcode' => 'EAN-A', 'qty_separated' => 5],
        ]);

        // 2 linhas no movements pra mesmo barcode (NF tem 2 itens iguais)
        $this->insertMovement('Z424', 'NF-007', '2026-04-27', 'EAN-A', 3);
        $this->insertMovement('Z424', 'NF-007', '2026-04-27', 'EAN-A', 2);

        $result = $this->service->validate($reloc, 'NF-007', '2026-04-27');

        $this->assertCount(1, $result['matched']);
        $this->assertSame(5, $result['matched'][0]['qty_in_invoice']);
        $this->assertFalse($result['has_discrepancies']);
    }

    private function createRelocationInSeparation(array $items): Relocation
    {
        $reloc = Relocation::create([
            'ulid' => (string) Str::ulid(),
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'priority' => 'normal',
            'deadline_days' => 3,
            'status' => RelocationStatus::IN_SEPARATION->value,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        foreach ($items as $i) {
            RelocationItem::create([
                'relocation_id' => $reloc->id,
                'product_reference' => $i['barcode'],
                'product_name' => $i['name'] ?? 'Produto teste',
                'barcode' => $i['barcode'],
                'qty_requested' => $i['qty_separated'],
                'qty_separated' => $i['qty_separated'],
                'qty_received' => 0,
            ]);
        }

        return $reloc->fresh(['items', 'originStore']);
    }

    private function insertMovement(string $storeCode, string $invoice, string $date, string $barcode, int $qty): void
    {
        DB::table('movements')->insert([
            'movement_code' => 5,
            'entry_exit' => 'S',
            'store_code' => $storeCode,
            'invoice_number' => $invoice,
            'movement_date' => $date,
            'barcode' => $barcode,
            'quantity' => $qty,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
