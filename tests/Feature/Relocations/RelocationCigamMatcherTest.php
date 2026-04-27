<?php

namespace Tests\Feature\Relocations;

use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\RelocationItem;
use App\Models\RelocationType;
use App\Models\Store;
use App\Services\RelocationCigamMatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class RelocationCigamMatcherTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $origin;
    protected Store $destination;
    protected RelocationType $type;
    protected RelocationCigamMatcherService $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->origin = Store::factory()->create(['code' => 'ZORI', 'network_id' => 1]);
        $this->destination = Store::factory()->create(['code' => 'ZDST', 'network_id' => 1]);
        $this->type = RelocationType::firstOrCreate(['code' => 'PLAN'], ['name' => 'P', 'is_active' => true, 'sort_order' => 1]);
        $this->matcher = app(RelocationCigamMatcherService::class);
    }

    protected function makeInTransit(string $invoice, array $items): Relocation
    {
        $r = Relocation::create([
            'ulid' => (string) Str::ulid(),
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'priority' => 'normal',
            'status' => RelocationStatus::IN_TRANSIT->value,
            'invoice_number' => $invoice,
            'in_transit_at' => now(),
            'created_by_user_id' => $this->adminUser->id,
        ]);

        foreach ($items as $i) {
            RelocationItem::create([
                'relocation_id' => $r->id,
                'product_reference' => $i['ref'],
                'barcode' => $i['barcode'],
                'qty_requested' => $i['qty'],
                'qty_separated' => $i['qty'],
                'qty_received' => 0,
                'dispatched_quantity' => 0,
                'received_quantity' => 0,
            ]);
        }

        return $r->fresh(['items']);
    }

    protected function seedMovement(string $store, string $invoice, string $entryExit, string $barcode, int $qty): void
    {
        DB::table('movements')->insert([
            'movement_date' => now()->toDateString(),
            'movement_time' => '10:00:00',
            'store_code' => $store,
            'invoice_number' => $invoice,
            'movement_code' => 5,
            'entry_exit' => $entryExit,
            'barcode' => $barcode,
            'ref_size' => 'X',
            'quantity' => $qty,
            'sale_price' => 0,
            'cost_price' => 0,
            'realized_value' => 0,
            'discount_value' => 0,
            'net_value' => 0,
            'net_quantity' => $entryExit === 'S' ? -$qty : $qty,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_match_origem_marca_dispatched_at_e_quantity(): void
    {
        $r = $this->makeInTransit('NF-1', [
            ['ref' => 'A', 'barcode' => 'BC-A', 'qty' => 5],
        ]);
        $this->seedMovement('ZORI', 'NF-1', 'S', 'BC-A', 5);

        $matched = $this->matcher->matchOriginDispatch($r);

        $this->assertEquals(1, $matched);
        $r->refresh();
        $this->assertNotNull($r->cigam_dispatched_at);
        $this->assertNull($r->cigam_received_at);
        $this->assertEquals(5, $r->items->first()->fresh()->dispatched_quantity);
    }

    public function test_match_destino_marca_received_at_e_quantity(): void
    {
        $r = $this->makeInTransit('NF-2', [
            ['ref' => 'B', 'barcode' => 'BC-B', 'qty' => 3],
        ]);
        $this->seedMovement('ZDST', 'NF-2', 'E', 'BC-B', 3);

        $matched = $this->matcher->matchDestinationReceipt($r);

        $this->assertEquals(1, $matched);
        $r->refresh();
        $this->assertNull($r->cigam_dispatched_at);
        $this->assertNotNull($r->cigam_received_at);
        $this->assertEquals(3, $r->items->first()->fresh()->received_quantity);
    }

    public function test_match_e_idempotente(): void
    {
        $r = $this->makeInTransit('NF-3', [
            ['ref' => 'C', 'barcode' => 'BC-C', 'qty' => 2],
        ]);
        $this->seedMovement('ZORI', 'NF-3', 'S', 'BC-C', 2);

        $first = $this->matcher->matchOriginDispatch($r);
        $r->refresh();
        $second = $this->matcher->matchOriginDispatch($r);

        $this->assertEquals(1, $first);
        $this->assertEquals(0, $second); // 2ª chamada já tem timestamp setado
    }

    public function test_aderencia_parcial_dispatched_menor_que_requested(): void
    {
        $r = $this->makeInTransit('NF-4', [
            ['ref' => 'D', 'barcode' => 'BC-D', 'qty' => 5],
        ]);
        // Origem despachou só 3 das 5 unidades
        $this->seedMovement('ZORI', 'NF-4', 'S', 'BC-D', 3);

        $this->matcher->matchOriginDispatch($r);
        $item = $r->items->first()->fresh();

        $this->assertEquals(3, $item->dispatched_quantity);
        $this->assertEquals(5, $item->qty_requested);
        $this->assertEquals(60.0, $item->dispatch_adherence); // 3/5 = 60%
    }

    public function test_match_all_pending_processa_2_pontas(): void
    {
        $r = $this->makeInTransit('NF-BOTH', [
            ['ref' => 'X', 'barcode' => 'BC-X', 'qty' => 2],
        ]);
        $this->seedMovement('ZORI', 'NF-BOTH', 'S', 'BC-X', 2);
        $this->seedMovement('ZDST', 'NF-BOTH', 'E', 'BC-X', 2);

        $result = $this->matcher->matchAllPending();

        $this->assertEquals(1, $result['relocations_checked']);
        $this->assertEquals(1, $result['dispatched_matched']);
        $this->assertEquals(1, $result['received_matched']);

        $r->refresh();
        $this->assertNotNull($r->cigam_dispatched_at);
        $this->assertNotNull($r->cigam_received_at);
    }

    public function test_match_ignora_invoice_diferente(): void
    {
        $r = $this->makeInTransit('NF-A', [
            ['ref' => 'X', 'barcode' => 'BC-X', 'qty' => 1],
        ]);
        $this->seedMovement('ZORI', 'NF-OUTRA', 'S', 'BC-X', 1); // NF errada

        $matched = $this->matcher->matchOriginDispatch($r);

        $this->assertEquals(0, $matched);
        $this->assertNull($r->fresh()->cigam_dispatched_at);
    }
}
