<?php

namespace Tests\Feature\Relocations;

use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\RelocationType;
use App\Models\Store;
use App\Services\RelocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class RelocationServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $origin;
    protected Store $destination;
    protected RelocationType $type;
    protected RelocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->origin = Store::factory()->create(['code' => 'Z424']);
        $this->destination = Store::factory()->create(['code' => 'Z423']);
        $this->type = RelocationType::firstOrCreate(
            ['code' => 'PLANEJAMENTO'],
            ['name' => 'Planejamento', 'is_active' => true, 'sort_order' => 10]
        );
        $this->service = app(RelocationService::class);
    }

    public function test_create_persiste_cabecalho_items_e_history_inicial(): void
    {
        $r = $this->service->create([
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'title' => 'Teste',
            'priority' => 'high',
            'items' => [
                ['product_reference' => 'A', 'qty_requested' => 5],
                ['product_reference' => 'B', 'qty_requested' => 3],
            ],
        ], $this->adminUser);

        $this->assertEquals('draft', $r->status->value);
        $this->assertEquals(2, $r->items()->count());
        $this->assertEquals(8, $r->items()->sum('qty_requested'));
        $this->assertNotNull($r->ulid);
        $this->assertDatabaseHas('relocation_status_histories', [
            'relocation_id' => $r->id,
            'from_status' => null,
            'to_status' => 'draft',
        ]);
    }

    public function test_create_rejeita_origem_igual_destino(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create([
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->origin->id,
            'items' => [['product_reference' => 'A', 'qty_requested' => 1]],
        ], $this->adminUser);
    }

    public function test_create_rejeita_qty_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create([
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'items' => [['product_reference' => 'A', 'qty_requested' => 0]],
        ], $this->adminUser);
    }

    public function test_update_em_draft_permite_alterar_tudo(): void
    {
        $r = $this->service->create([
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'priority' => 'normal',
            'items' => [['product_reference' => 'A', 'qty_requested' => 1]],
        ], $this->adminUser);

        $updated = $this->service->update($r, [
            'title' => 'Novo título',
            'priority' => 'urgent',
            'observations' => 'obs',
        ], $this->adminUser);

        $this->assertEquals('Novo título', $updated->title);
        $this->assertEquals('urgent', $updated->priority->value);
    }

    public function test_update_filtra_campos_sensiveis(): void
    {
        $r = $this->service->create([
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'items' => [['product_reference' => 'A', 'qty_requested' => 1]],
        ], $this->adminUser);

        $updated = $this->service->update($r, [
            'status' => 'completed',         // não pode mudar status
            'transfer_id' => 999,            // não pode mudar transfer_id
            'cigam_received_at' => now(),    // não pode mudar
            'observations' => 'ok',           // pode
        ], $this->adminUser);

        $this->assertEquals('draft', $updated->status->value);
        $this->assertNull($updated->transfer_id);
        $this->assertNull($updated->cigam_received_at);
        $this->assertEquals('ok', $updated->observations);
    }

    public function test_softDelete_exige_motivo_minimo(): void
    {
        $r = $this->service->create([
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'items' => [['product_reference' => 'A', 'qty_requested' => 1]],
        ], $this->adminUser);

        $this->expectException(ValidationException::class);
        $this->service->softDelete($r, $this->adminUser, '');
    }
}
