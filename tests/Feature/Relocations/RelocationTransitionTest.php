<?php

namespace Tests\Feature\Relocations;

use App\Enums\RelocationPriority;
use App\Enums\RelocationStatus;
use App\Events\RelocationStatusChanged;
use App\Models\Relocation;
use App\Models\RelocationItem;
use App\Models\RelocationType;
use App\Models\Store;
use App\Models\Transfer;
use App\Services\RelocationService;
use App\Services\RelocationTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class RelocationTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $origin;
    protected Store $destination;
    protected RelocationType $type;
    protected RelocationService $service;
    protected RelocationTransitionService $transitionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->origin = Store::factory()->create(['code' => 'Z424', 'name' => 'Origem']);
        $this->destination = Store::factory()->create(['code' => 'Z423', 'name' => 'Destino']);
        $this->type = RelocationType::firstOrCreate(
            ['code' => 'PLANEJAMENTO'],
            ['name' => 'Planejamento', 'is_active' => true, 'sort_order' => 10]
        );
        $this->service = app(RelocationService::class);
        $this->transitionService = app(RelocationTransitionService::class);
    }

    protected function makeRelocation(RelocationStatus $status = RelocationStatus::DRAFT, array $items = []): Relocation
    {
        $r = Relocation::create([
            'ulid' => (string) Str::ulid(),
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'priority' => RelocationPriority::NORMAL->value,
            'status' => $status->value,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        foreach ($items ?: [['ref' => 'A', 'qty' => 3]] as $i) {
            RelocationItem::create([
                'relocation_id' => $r->id,
                'product_reference' => $i['ref'],
                'qty_requested' => $i['qty'],
                'qty_separated' => $i['separated'] ?? 0,
                'qty_received' => 0,
                'dispatched_quantity' => 0,
                'received_quantity' => 0,
            ]);
        }

        return $r->fresh(['items']);
    }

    // ------------------------------------------------------------------
    // Happy paths
    // ------------------------------------------------------------------

    public function test_draft_to_requested_grava_history_e_timestamp(): void
    {
        $r = $this->makeRelocation();
        $updated = $this->transitionService->transition($r, RelocationStatus::REQUESTED, $this->adminUser);

        $this->assertEquals(RelocationStatus::REQUESTED, $updated->status);
        $this->assertNotNull($updated->requested_at);
        $this->assertDatabaseHas('relocation_status_histories', [
            'relocation_id' => $r->id,
            'from_status' => 'draft',
            'to_status' => 'requested',
        ]);
    }

    public function test_requested_to_approved_seta_approved_by(): void
    {
        $r = $this->makeRelocation(RelocationStatus::REQUESTED);
        $updated = $this->transitionService->transition($r, RelocationStatus::APPROVED, $this->adminUser);

        $this->assertEquals($this->adminUser->id, $updated->approved_by_user_id);
        $this->assertNotNull($updated->approved_at);
    }

    public function test_in_separation_to_in_transit_cria_transfer_e_exige_nf(): void
    {
        $r = $this->makeRelocation(RelocationStatus::IN_SEPARATION, [
            ['ref' => 'A', 'qty' => 5, 'separated' => 5],
        ]);

        $updated = $this->transitionService->transition($r, RelocationStatus::IN_TRANSIT, $this->adminUser, null, [
            'invoice_number' => 'NF-9999',
        ]);

        $this->assertEquals('NF-9999', $updated->invoice_number);
        $this->assertNotNull($updated->transfer_id);

        $transfer = Transfer::find($updated->transfer_id);
        $this->assertEquals('relocation', $transfer->transfer_type);
        $this->assertEquals('in_transit', $transfer->status);
        $this->assertEquals('NF-9999', $transfer->invoice_number);
        $this->assertEquals($r->id, $transfer->relocation_id);
    }

    public function test_dispatch_sem_nf_falha(): void
    {
        $r = $this->makeRelocation(RelocationStatus::IN_SEPARATION, [
            ['ref' => 'A', 'qty' => 3, 'separated' => 3],
        ]);

        $this->expectException(ValidationException::class);
        $this->transitionService->transition($r, RelocationStatus::IN_TRANSIT, $this->adminUser);
    }

    public function test_dispatch_sem_qty_separada_falha(): void
    {
        $r = $this->makeRelocation(RelocationStatus::IN_SEPARATION, [
            ['ref' => 'A', 'qty' => 3, 'separated' => 0],
        ]);

        $this->expectException(ValidationException::class);
        $this->transitionService->transition($r, RelocationStatus::IN_TRANSIT, $this->adminUser, null, [
            'invoice_number' => 'NF-1',
        ]);
    }

    public function test_in_transit_to_partial_aplica_qty_received_e_confirma_transfer(): void
    {
        $r = $this->makeRelocation(RelocationStatus::IN_SEPARATION, [
            ['ref' => 'A', 'qty' => 5, 'separated' => 5],
        ]);
        $r = $this->transitionService->transition($r, RelocationStatus::IN_TRANSIT, $this->adminUser, null, [
            'invoice_number' => 'NF-PART',
        ]);

        $itemId = $r->items->first()->id;

        $r = $this->transitionService->transition($r, RelocationStatus::PARTIAL, $this->adminUser, 'faltou 1', [
            'receiver_name' => 'João',
            'received_items' => [
                ['id' => $itemId, 'qty_received' => 4, 'reason_code' => 'MISSING'],
            ],
        ]);

        $this->assertEquals(RelocationStatus::PARTIAL, $r->status);
        $item = $r->items->first()->fresh();
        $this->assertEquals(4, $item->qty_received);
        $this->assertEquals('MISSING', $item->reason_code);

        $transfer = Transfer::find($r->transfer_id);
        $this->assertEquals('confirmed', $transfer->status);
        $this->assertEquals('João', $transfer->receiver_name);
        $this->assertEquals($this->adminUser->id, $transfer->confirmed_by_user_id);
    }

    public function test_received_quantity_nao_pode_exceder_separated(): void
    {
        $r = $this->makeRelocation(RelocationStatus::IN_SEPARATION, [
            ['ref' => 'A', 'qty' => 3, 'separated' => 3],
        ]);
        $r = $this->transitionService->transition($r, RelocationStatus::IN_TRANSIT, $this->adminUser, null, [
            'invoice_number' => 'NF-X',
        ]);
        $itemId = $r->items->first()->id;

        $this->expectException(ValidationException::class);
        $this->transitionService->transition($r, RelocationStatus::COMPLETED, $this->adminUser, null, [
            'receiver_name' => 'Maria',
            'received_items' => [['id' => $itemId, 'qty_received' => 99]],
        ]);
    }

    public function test_reason_code_invalido_e_rejeitado(): void
    {
        $r = $this->makeRelocation(RelocationStatus::IN_SEPARATION, [
            ['ref' => 'A', 'qty' => 3, 'separated' => 3],
        ]);
        $r = $this->transitionService->transition($r, RelocationStatus::IN_TRANSIT, $this->adminUser, null, [
            'invoice_number' => 'NF-X',
        ]);
        $itemId = $r->items->first()->id;

        $this->expectException(ValidationException::class);
        $this->transitionService->transition($r, RelocationStatus::PARTIAL, $this->adminUser, 'teste', [
            'receiver_name' => 'X',
            'received_items' => [['id' => $itemId, 'qty_received' => 0, 'reason_code' => 'FOOBAR']],
        ]);
    }

    public function test_cancelamento_exige_motivo(): void
    {
        $r = $this->makeRelocation(RelocationStatus::REQUESTED);

        $this->expectException(ValidationException::class);
        $this->transitionService->transition($r, RelocationStatus::CANCELLED, $this->adminUser);
    }

    public function test_cancelamento_pos_in_transit_e_bloqueado(): void
    {
        $r = $this->makeRelocation(RelocationStatus::IN_SEPARATION, [
            ['ref' => 'A', 'qty' => 3, 'separated' => 3],
        ]);
        $r = $this->transitionService->transition($r, RelocationStatus::IN_TRANSIT, $this->adminUser, null, [
            'invoice_number' => 'NF-Z',
        ]);

        $this->expectException(ValidationException::class);
        $this->transitionService->transition($r, RelocationStatus::CANCELLED, $this->adminUser, 'motivo');
    }

    public function test_transicao_invalida_e_rejeitada(): void
    {
        $r = $this->makeRelocation(RelocationStatus::DRAFT);

        $this->expectException(ValidationException::class);
        // draft → completed não é permitido (precisa passar por requested→approved→...)
        $this->transitionService->transition($r, RelocationStatus::COMPLETED, $this->adminUser);
    }

    public function test_rejected_e_terminal(): void
    {
        $r = $this->makeRelocation(RelocationStatus::REQUESTED);
        $r = $this->transitionService->transition($r, RelocationStatus::REJECTED, $this->adminUser, 'motivo da rejeição');

        $this->assertTrue($r->isTerminal());
        $this->assertNotNull($r->rejected_at);
        $this->assertEquals('motivo da rejeição', $r->rejected_reason);
    }

    public function test_evento_relocation_status_changed_e_disparado(): void
    {
        Event::fake([RelocationStatusChanged::class]);

        $r = $this->makeRelocation(RelocationStatus::DRAFT);
        $this->transitionService->transition($r, RelocationStatus::REQUESTED, $this->adminUser);

        Event::assertDispatched(RelocationStatusChanged::class, function ($e) use ($r) {
            return $e->relocation->id === $r->id
                && $e->fromStatus === RelocationStatus::DRAFT
                && $e->toStatus === RelocationStatus::REQUESTED;
        });
    }
}
