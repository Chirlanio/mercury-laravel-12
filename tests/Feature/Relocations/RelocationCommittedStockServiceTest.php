<?php

namespace Tests\Feature\Relocations;

use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\RelocationItem;
use App\Models\RelocationType;
use App\Models\Store;
use App\Services\RelocationCommittedStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class RelocationCommittedStockServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $origin;
    protected Store $destination;
    protected RelocationType $type;
    protected RelocationCommittedStockService $service;

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
        $this->service = app(RelocationCommittedStockService::class);
    }

    public function test_returns_empty_when_no_committing_relocations(): void
    {
        $result = $this->service->committedByBarcode($this->origin->id, ['EAN-001', 'EAN-002']);

        $this->assertEmpty($result);
    }

    public function test_sums_qty_requested_minus_separated_for_active_statuses(): void
    {
        // 2 remanejos abertos da mesma origem com o mesmo barcode:
        //   - DRAFT requested 3, separated 0 → committed 3
        //   - APPROVED requested 5, separated 2 → committed 3
        // Total esperado: 6
        $this->createRelocationWithItem(RelocationStatus::DRAFT, 'EAN-A', qtyRequested: 3, qtySeparated: 0);
        $this->createRelocationWithItem(RelocationStatus::APPROVED, 'EAN-A', qtyRequested: 5, qtySeparated: 2);

        $result = $this->service->committedByBarcode($this->origin->id, ['EAN-A']);

        $this->assertSame(6, $result['EAN-A']);
    }

    public function test_excludes_in_transit_status_from_committed(): void
    {
        // IN_TRANSIT é excluído deliberadamente — CIGAM já baixou nesse estágio.
        $this->createRelocationWithItem(RelocationStatus::IN_TRANSIT, 'EAN-B', qtyRequested: 5, qtySeparated: 0);

        $result = $this->service->committedByBarcode($this->origin->id, ['EAN-B']);

        $this->assertEmpty($result);
    }

    public function test_excludes_terminal_statuses(): void
    {
        $this->createRelocationWithItem(RelocationStatus::COMPLETED, 'EAN-C', qtyRequested: 5);
        $this->createRelocationWithItem(RelocationStatus::CANCELLED, 'EAN-C', qtyRequested: 2);
        $this->createRelocationWithItem(RelocationStatus::REJECTED, 'EAN-C', qtyRequested: 1);

        $result = $this->service->committedByBarcode($this->origin->id, ['EAN-C']);

        $this->assertEmpty($result);
    }

    public function test_returns_zero_when_qty_separated_equals_requested(): void
    {
        // Tudo já foi separado e está saindo — não conta como comprometido.
        $this->createRelocationWithItem(RelocationStatus::IN_SEPARATION, 'EAN-D', qtyRequested: 4, qtySeparated: 4);

        $result = $this->service->committedByBarcode($this->origin->id, ['EAN-D']);

        // Soma é 0 — pode aparecer como 0 ou nem aparecer dependendo de SQL.
        $this->assertSame(0, $result['EAN-D'] ?? 0);
    }

    public function test_isolates_committed_by_origin_store(): void
    {
        $otherOrigin = Store::factory()->create(['code' => 'Z425', 'network_id' => 1]);

        $this->createRelocationWithItem(RelocationStatus::DRAFT, 'EAN-E', qtyRequested: 3, originStore: $this->origin);
        $this->createRelocationWithItem(RelocationStatus::DRAFT, 'EAN-E', qtyRequested: 7, originStore: $otherOrigin);

        $resultOrigin = $this->service->committedByBarcode($this->origin->id, ['EAN-E']);
        $resultOther = $this->service->committedByBarcode($otherOrigin->id, ['EAN-E']);

        $this->assertSame(3, $resultOrigin['EAN-E']);
        $this->assertSame(7, $resultOther['EAN-E']);
    }

    public function test_excludes_specified_relocation_id(): void
    {
        $reloc1 = $this->createRelocationWithItem(RelocationStatus::DRAFT, 'EAN-F', qtyRequested: 4);
        $this->createRelocationWithItem(RelocationStatus::DRAFT, 'EAN-F', qtyRequested: 2);

        // Sem exclusão: total 6
        $all = $this->service->committedByBarcode($this->origin->id, ['EAN-F']);
        $this->assertSame(6, $all['EAN-F']);

        // Excluindo reloc1 (que comprometeu 4): sobra 2
        $excluding = $this->service->committedByBarcode($this->origin->id, ['EAN-F'], $reloc1->id);
        $this->assertSame(2, $excluding['EAN-F']);
    }

    public function test_committed_by_store_and_barcode_indexes_correctly(): void
    {
        $other = Store::factory()->create(['code' => 'Z425', 'network_id' => 1]);

        $this->createRelocationWithItem(RelocationStatus::DRAFT, 'EAN-G', qtyRequested: 2, originStore: $this->origin);
        $this->createRelocationWithItem(RelocationStatus::REQUESTED, 'EAN-G', qtyRequested: 5, originStore: $other);
        $this->createRelocationWithItem(RelocationStatus::DRAFT, 'EAN-H', qtyRequested: 1, originStore: $this->origin);

        $result = $this->service->committedByStoreAndBarcode(
            [$this->origin->id, $other->id],
            ['EAN-G', 'EAN-H'],
        );

        $this->assertSame(2, $result["{$this->origin->id}|EAN-G"]);
        $this->assertSame(5, $result["{$other->id}|EAN-G"]);
        $this->assertSame(1, $result["{$this->origin->id}|EAN-H"]);
    }

    private function createRelocationWithItem(
        RelocationStatus $status,
        string $barcode,
        int $qtyRequested = 1,
        int $qtySeparated = 0,
        ?Store $originStore = null,
    ): Relocation {
        $reloc = Relocation::create([
            'ulid' => (string) Str::ulid(),
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => ($originStore ?? $this->origin)->id,
            'destination_store_id' => $this->destination->id,
            'priority' => 'normal',
            'deadline_days' => 3,
            'status' => $status->value,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        RelocationItem::create([
            'relocation_id' => $reloc->id,
            'product_reference' => $barcode,
            'product_name' => 'Produto teste',
            'barcode' => $barcode,
            'qty_requested' => $qtyRequested,
            'qty_separated' => $qtySeparated,
            'qty_received' => 0,
        ]);

        return $reloc;
    }
}
