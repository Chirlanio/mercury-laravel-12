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

        // Garantir mesma rede pra tests existentes não falharem por causa
        // da validação cross-network adicionada na Fase 8.6
        $this->origin = Store::factory()->create(['code' => 'Z424', 'network_id' => 1]);
        $this->destination = Store::factory()->create(['code' => 'Z423', 'network_id' => 1]);
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

    public function test_clone_cria_novo_draft_a_partir_de_cancelado(): void
    {
        $original = $this->service->create([
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'title' => 'Original',
            'priority' => 'high',
            'observations' => 'Notas originais',
            'items' => [
                ['product_reference' => 'A', 'qty_requested' => 5],
                ['product_reference' => 'B', 'qty_requested' => 3],
            ],
        ], $this->adminUser);

        // Marca como cancelled diretamente (skip transition pra simplificar setup)
        $original->update(['status' => \App\Enums\RelocationStatus::CANCELLED->value]);

        $clone = $this->service->cloneFrom($original->fresh(['items']), $this->adminUser);

        $this->assertNotEquals($original->id, $clone->id);
        $this->assertEquals('draft', $clone->status->value);
        $this->assertEquals('high', $clone->priority->value);
        $this->assertEquals($this->origin->id, $clone->origin_store_id);
        $this->assertEquals($this->destination->id, $clone->destination_store_id);
        $this->assertStringContainsString('reaberto', $clone->title);
        $this->assertStringContainsString('Reaberto a partir do remanejo', $clone->observations);
        $this->assertEquals(2, $clone->items()->count());
        $this->assertEquals(8, $clone->items()->sum('qty_requested'));
        $this->assertEquals(0, $clone->items()->sum('qty_separated'));
    }

    public function test_clone_rejeita_status_nao_terminal(): void
    {
        $original = $this->service->create([
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'items' => [['product_reference' => 'A', 'qty_requested' => 1]],
        ], $this->adminUser);

        // status=draft (não-terminal)
        $this->expectException(ValidationException::class);
        $this->service->cloneFrom($original, $this->adminUser);
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

    public function test_create_rejeita_redes_diferentes(): void
    {
        // Cria stores com redes distintas
        $arezzo = Store::factory()->create(['code' => 'ZAREZ', 'network_id' => 1]);
        $schutz = Store::factory()->create(['code' => 'ZSCHU', 'network_id' => 4]);

        $this->expectException(ValidationException::class);
        try {
            $this->service->create([
                'relocation_type_id' => $this->type->id,
                'origin_store_id' => $arezzo->id,
                'destination_store_id' => $schutz->id,
                'items' => [['product_reference' => 'A', 'qty_requested' => 1]],
            ], $this->adminUser);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('destination_store_id', $e->errors());
            $this->assertStringContainsString('mesma rede', $e->errors()['destination_store_id'][0]);
            throw $e;
        }
    }

    public function test_create_aceita_mesma_rede(): void
    {
        // Setup explícito: ambas na rede 1
        $arezzo1 = Store::factory()->create(['code' => 'ZA1', 'network_id' => 1]);
        $arezzo2 = Store::factory()->create(['code' => 'ZA2', 'network_id' => 1]);

        $r = $this->service->create([
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $arezzo1->id,
            'destination_store_id' => $arezzo2->id,
            'items' => [['product_reference' => 'X', 'qty_requested' => 2]],
        ], $this->adminUser);

        $this->assertNotNull($r->id);
        $this->assertEquals($arezzo1->id, $r->origin_store_id);
        $this->assertEquals($arezzo2->id, $r->destination_store_id);
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
