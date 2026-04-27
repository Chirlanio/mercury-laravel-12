<?php

namespace Tests\Feature\Relocations;

use App\Enums\Permission;
use App\Enums\RelocationStatus;
use App\Models\Relocation;
use App\Models\RelocationType;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class RelocationControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $origin;
    protected Store $destination;
    protected RelocationType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->origin = Store::factory()->create(['code' => 'Z424']);
        $this->destination = Store::factory()->create(['code' => 'Z423']);
        $this->type = RelocationType::firstOrCreate(['code' => 'PLAN'], ['name' => 'P', 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_index_renderiza_pagina(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('relocations.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Relocations/Index'));
    }

    public function test_index_exige_permission(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('relocations.index'));
        // USER tem VIEW_RELOCATIONS, então deve passar
        $response->assertOk();
    }

    public function test_store_cria_remanejo_com_items(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('relocations.store'), [
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'title' => 'CTL Test',
            'priority' => 'normal',
            'items' => [
                ['product_reference' => 'X', 'qty_requested' => 5],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('relocations', [
            'title' => 'CTL Test',
            'status' => 'draft',
        ]);
    }

    public function test_store_rejeita_origem_igual_destino(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('relocations.store'), [
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->origin->id, // mesmo id
            'priority' => 'normal',
            'items' => [['product_reference' => 'X', 'qty_requested' => 1]],
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_show_retorna_json_com_items_e_history(): void
    {
        $r = $this->createRelocation();

        $response = $this->actingAs($this->adminUser)->get(route('relocations.show', $r->ulid));

        $response->assertOk();
        $response->assertJsonPath('relocation.id', $r->id);
        $response->assertJsonStructure([
            'relocation' => ['id', 'ulid', 'items', 'status_history'],
        ]);
    }

    public function test_transition_endpoint_atualiza_status(): void
    {
        $r = $this->createRelocation();

        $response = $this->actingAs($this->adminUser)->post(
            route('relocations.transition', $r->ulid),
            ['to_status' => RelocationStatus::REQUESTED->value]
        );

        $response->assertRedirect();
        $this->assertEquals('requested', $r->fresh()->status->value);
    }

    public function test_transition_rejeita_status_invalido(): void
    {
        $r = $this->createRelocation();

        $response = $this->actingAs($this->adminUser)->post(
            route('relocations.transition', $r->ulid),
            ['to_status' => 'foobar']
        );

        $response->assertSessionHasErrors('to_status');
    }

    public function test_destroy_pede_motivo(): void
    {
        $r = $this->createRelocation();

        $response = $this->actingAs($this->adminUser)->delete(route('relocations.destroy', $r->ulid), [
            'reason' => 'erro de cadastro',
        ]);

        $response->assertRedirect();
        $this->assertNotNull($r->fresh()->deleted_at);
    }

    public function test_dashboard_renderiza(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('relocations.dashboard'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Relocations/Dashboard'));
    }

    public function test_statistics_endpoint_retorna_json(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson(route('relocations.statistics'));
        $response->assertOk();
        $response->assertJsonStructure(['total', 'draft', 'requested', 'in_transit', 'overdue']);
    }

    public function test_import_template_baixa_csv_barcode(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('relocations.import.template', ['mode' => 'barcode']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Origem;Destino;Codigo_Barras;Quantidade', $response->getContent());
    }

    public function test_import_template_baixa_csv_reference(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('relocations.import.template', ['mode' => 'reference']));
        $response->assertOk();
        $this->assertStringContainsString('Origem;Destino;Referencia;Tamanho;Quantidade', $response->getContent());
    }

    protected function createRelocation(): Relocation
    {
        return app(\App\Services\RelocationService::class)->create([
            'relocation_type_id' => $this->type->id,
            'origin_store_id' => $this->origin->id,
            'destination_store_id' => $this->destination->id,
            'priority' => 'normal',
            'items' => [['product_reference' => 'A', 'qty_requested' => 3]],
        ], $this->adminUser);
    }
}
