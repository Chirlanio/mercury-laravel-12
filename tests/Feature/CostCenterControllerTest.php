<?php

namespace Tests\Feature;

use App\Models\CostCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CostCenterControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        // Migration de seed popula 24 CCs reais — limpa para testes de CRUD isolados
        CostCenter::query()->delete();
    }

    protected function createCostCenter(array $overrides = []): CostCenter
    {
        return CostCenter::create(array_merge([
            'code' => 'CC-'.str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'name' => 'Centro '.rand(100, 999),
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('cost-centers.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('CostCenters/Index'));
    }

    public function test_regular_user_cannot_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('cost-centers.index'));
        $response->assertStatus(403);
    }

    public function test_index_hides_soft_deleted(): void
    {
        $active = $this->createCostCenter(['code' => 'ACTIVE-1']);
        $deleted = $this->createCostCenter(['code' => 'DEL-1']);
        $deleted->forceFill(['deleted_at' => now(), 'deleted_by_user_id' => $this->adminUser->id, 'deleted_reason' => 'testing'])->save();

        $response = $this->actingAs($this->adminUser)->get(route('cost-centers.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('costCenters.data', 1));
    }

    public function test_index_can_search_by_code_and_name(): void
    {
        $this->createCostCenter(['code' => 'ADM-100', 'name' => 'Administrativo']);
        $this->createCostCenter(['code' => 'FIN-100', 'name' => 'Financeiro']);

        $response = $this->actingAs($this->adminUser)
            ->get(route('cost-centers.index', ['search' => 'ADM']));

        $response->assertInertia(fn ($page) => $page->has('costCenters.data', 1)
            ->where('costCenters.data.0.code', 'ADM-100'));
    }

    public function test_admin_can_create_cost_center(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('cost-centers.store'), [
            'code' => 'NEW-001',
            'name' => 'Novo CC',
            'description' => 'Descrição de teste',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('cost_centers', [
            'code' => 'NEW-001',
            'name' => 'Novo CC',
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    public function test_create_rejects_duplicate_code(): void
    {
        $this->createCostCenter(['code' => 'DUP-001']);

        $response = $this->actingAs($this->adminUser)->post(route('cost-centers.store'), [
            'code' => 'DUP-001',
            'name' => 'Tentativa duplicada',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertEquals(1, CostCenter::count());
    }

    public function test_create_rejects_parent_that_does_not_exist(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('cost-centers.store'), [
            'code' => 'CHILD-001',
            'name' => 'Com pai fantasma',
            'parent_id' => 999999,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_create_with_valid_parent(): void
    {
        $parent = $this->createCostCenter(['code' => 'PARENT-1']);

        $response = $this->actingAs($this->adminUser)->post(route('cost-centers.store'), [
            'code' => 'CHILD-1',
            'name' => 'Filho válido',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cost_centers', [
            'code' => 'CHILD-1',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_show_returns_json(): void
    {
        $cc = $this->createCostCenter(['code' => 'SHOW-1']);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('cost-centers.show', $cc->id));

        $response->assertStatus(200);
        $response->assertJsonPath('costCenter.id', $cc->id);
        $response->assertJsonPath('costCenter.code', 'SHOW-1');
    }

    public function test_show_returns_404_for_deleted(): void
    {
        $cc = $this->createCostCenter();
        $cc->forceFill(['deleted_at' => now(), 'deleted_by_user_id' => $this->adminUser->id, 'deleted_reason' => 'testing'])->save();

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('cost-centers.show', $cc->id));

        $response->assertStatus(404);
    }

    public function test_admin_can_update(): void
    {
        $cc = $this->createCostCenter(['name' => 'Nome Antigo']);

        $response = $this->actingAs($this->adminUser)
            ->put(route('cost-centers.update', $cc->id), [
                'name' => 'Nome Novo',
                'description' => 'Nova descrição',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cost_centers', [
            'id' => $cc->id,
            'name' => 'Nome Novo',
            'updated_by_user_id' => $this->adminUser->id,
        ]);
    }

    public function test_update_blocks_cycle_in_parent(): void
    {
        $grandparent = $this->createCostCenter(['code' => 'GP']);
        $parent = $this->createCostCenter(['code' => 'P', 'parent_id' => $grandparent->id]);
        $child = $this->createCostCenter(['code' => 'C', 'parent_id' => $parent->id]);

        // Tenta fazer o avô virar filho do neto — deve bloquear
        $response = $this->actingAs($this->adminUser)
            ->put(route('cost-centers.update', $grandparent->id), [
                'parent_id' => $child->id,
            ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertNull($grandparent->fresh()->parent_id);
    }

    public function test_update_blocks_self_as_parent(): void
    {
        $cc = $this->createCostCenter();

        $response = $this->actingAs($this->adminUser)
            ->put(route('cost-centers.update', $cc->id), [
                'parent_id' => $cc->id,
            ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_admin_can_soft_delete(): void
    {
        $cc = $this->createCostCenter();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('cost-centers.destroy', $cc->id), [
                'deleted_reason' => 'Duplicado',
            ]);

        $response->assertRedirect();
        $this->assertNotNull($cc->fresh()->deleted_at);
        $this->assertEquals($this->adminUser->id, $cc->fresh()->deleted_by_user_id);
    }

    public function test_delete_requires_reason(): void
    {
        $cc = $this->createCostCenter();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('cost-centers.destroy', $cc->id), [
                'deleted_reason' => 'x',  // menos de 3 chars
            ]);

        $response->assertSessionHasErrors('deleted_reason');
        $this->assertNull($cc->fresh()->deleted_at);
    }

    public function test_delete_blocked_when_has_active_children(): void
    {
        $parent = $this->createCostCenter(['code' => 'PARENT-X']);
        $this->createCostCenter(['code' => 'CHILD-X', 'parent_id' => $parent->id]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('cost-centers.destroy', $parent->id), [
                'deleted_reason' => 'Tentativa',
            ]);

        $response->assertSessionHasErrors();
        $this->assertNull($parent->fresh()->deleted_at);
    }

    public function test_statistics_endpoint_returns_aggregates(): void
    {
        $this->createCostCenter(['code' => 'A', 'is_active' => true]);
        $this->createCostCenter(['code' => 'B', 'is_active' => false]);
        $parent = $this->createCostCenter(['code' => 'P', 'is_active' => true]);
        $this->createCostCenter(['code' => 'CH', 'parent_id' => $parent->id, 'is_active' => true]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('cost-centers.statistics'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['total', 'active', 'inactive', 'with_parent', 'roots']);
        $response->assertJsonPath('total', 4);
        $response->assertJsonPath('active', 3);
        $response->assertJsonPath('inactive', 1);
        $response->assertJsonPath('with_parent', 1);
        $response->assertJsonPath('roots', 3);
    }
}
