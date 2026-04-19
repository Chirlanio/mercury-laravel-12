<?php

namespace Tests\Feature;

use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use App\Models\AccountingClass;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ManagementClassControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AccountingClass $accountingLeaf;

    protected AccountingClass $accountingGroup;

    protected CostCenter $costCenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        // O seed do plano real (Grupo Meia Sola) popula accounting_classes — refs úteis
        $this->accountingGroup = AccountingClass::where('code', '4.2.1.04')->firstOrFail();  // Despesas Administrativas (sintético)
        $this->accountingLeaf = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail(); // Telefonia (analítica)

        $this->costCenter = CostCenter::create([
            'code' => 'CC-MGMT-TEST',
            'name' => 'CC de Teste',
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        // Migration de seed popula 182 classes gerenciais reais — limpa para testes isolados
        ManagementClass::query()->delete();
    }

    protected function createClass(array $overrides = []): ManagementClass
    {
        return ManagementClass::create(array_merge([
            'code' => 'MC-'.uniqid(),
            'name' => 'Conta Gerencial Teste',
            'accepts_entries' => true,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('management-classes.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('ManagementClasses/Index'));
    }

    public function test_regular_user_cannot_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('management-classes.index'));
        $response->assertStatus(403);
    }

    public function test_create_new_class(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('management-classes.store'), [
            'code' => 'MGMT-001',
            'name' => 'TI - Desenvolvimento',
            'accepts_entries' => true,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('management_classes', [
            'code' => 'MGMT-001',
            'name' => 'TI - Desenvolvimento',
        ]);
    }

    public function test_create_with_accounting_link_to_leaf(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('management-classes.store'), [
            'code' => 'MGMT-LNK',
            'name' => 'Vinculada a conta contábil',
            'accounting_class_id' => $this->accountingLeaf->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('management_classes', [
            'code' => 'MGMT-LNK',
            'accounting_class_id' => $this->accountingLeaf->id,
        ]);
    }

    public function test_create_rejects_accounting_link_to_synthetic_group(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('management-classes.store'), [
            'code' => 'MGMT-BAD',
            'name' => 'Tentativa com agrupador',
            'accounting_class_id' => $this->accountingGroup->id,
        ]);

        $response->assertSessionHasErrors('accounting_class_id');
    }

    public function test_create_with_cost_center_default(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('management-classes.store'), [
            'code' => 'MGMT-CC',
            'name' => 'Com CC default',
            'cost_center_id' => $this->costCenter->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('management_classes', [
            'code' => 'MGMT-CC',
            'cost_center_id' => $this->costCenter->id,
        ]);
    }

    public function test_create_rejects_duplicate_code(): void
    {
        $this->createClass(['code' => 'DUP-MGMT']);

        $response = $this->actingAs($this->adminUser)->post(route('management-classes.store'), [
            'code' => 'DUP-MGMT',
            'name' => 'Duplicado',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_create_rejects_parent_that_is_leaf(): void
    {
        $leaf = $this->createClass(['code' => 'MGMT-LEAF', 'accepts_entries' => true]);

        $response = $this->actingAs($this->adminUser)->post(route('management-classes.store'), [
            'code' => 'MGMT-CHILD',
            'name' => 'Filha de folha',
            'parent_id' => $leaf->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_update_blocks_cycle_in_parent(): void
    {
        $gp = $this->createClass(['code' => 'GP-MC', 'accepts_entries' => false]);
        $p = $this->createClass(['code' => 'P-MC', 'parent_id' => $gp->id, 'accepts_entries' => false]);
        $c = $this->createClass(['code' => 'C-MC', 'parent_id' => $p->id, 'accepts_entries' => false]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('management-classes.update', $gp->id), [
                'parent_id' => $c->id,
            ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_update_blocks_becoming_leaf_with_children(): void
    {
        $parent = $this->createClass(['code' => 'MC-P-UPD', 'accepts_entries' => false]);
        $this->createClass(['code' => 'MC-CH-UPD', 'parent_id' => $parent->id, 'accepts_entries' => true]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('management-classes.update', $parent->id), [
                'accepts_entries' => true,
            ]);

        $response->assertSessionHasErrors('accepts_entries');
    }

    public function test_show_returns_json(): void
    {
        $c = $this->createClass([
            'code' => 'SHOW-MC',
            'accounting_class_id' => $this->accountingLeaf->id,
            'cost_center_id' => $this->costCenter->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('management-classes.show', $c->id));

        $response->assertStatus(200);
        $response->assertJsonPath('managementClass.code', 'SHOW-MC');
        $response->assertJsonPath('managementClass.has_accounting_link', true);
        $this->assertStringContainsString(
            $this->accountingLeaf->code,
            $response->json('managementClass.accounting_class_label')
        );
    }

    public function test_delete_with_reason(): void
    {
        $c = $this->createClass(['code' => 'DEL-MC']);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('management-classes.destroy', $c->id), [
                'deleted_reason' => 'Motivo válido',
            ]);

        $response->assertRedirect();
        $this->assertNotNull($c->fresh()->deleted_at);
    }

    public function test_delete_blocked_with_active_children(): void
    {
        $parent = $this->createClass(['code' => 'DEL-P-MC', 'accepts_entries' => false]);
        $this->createClass(['code' => 'DEL-C-MC', 'parent_id' => $parent->id]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('management-classes.destroy', $parent->id), [
                'deleted_reason' => 'Tentativa',
            ]);

        $response->assertSessionHasErrors();
        $this->assertNull($parent->fresh()->deleted_at);
    }

    public function test_statistics_counts_linked_and_unlinked(): void
    {
        $this->createClass(['code' => 'STAT-1', 'accounting_class_id' => $this->accountingLeaf->id]);
        $this->createClass(['code' => 'STAT-2']);
        $this->createClass(['code' => 'STAT-3', 'accounting_class_id' => $this->accountingLeaf->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('management-classes.statistics'));

        $response->assertStatus(200);
        $response->assertJsonPath('total', 3);
        $response->assertJsonPath('linked_to_accounting', 2);
        $response->assertJsonPath('unlinked_from_accounting', 1);
    }

    public function test_tree_endpoint_builds_hierarchy(): void
    {
        $root = $this->createClass(['code' => 'TREE-ROOT', 'accepts_entries' => false]);
        $this->createClass(['code' => 'TREE-CH1', 'parent_id' => $root->id]);
        $this->createClass(['code' => 'TREE-CH2', 'parent_id' => $root->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('management-classes.tree'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['tree']);

        $rootNode = collect($response->json('tree'))->firstWhere('code', 'TREE-ROOT');
        $this->assertNotNull($rootNode);
        $this->assertCount(2, $rootNode['children']);
    }

    public function test_index_filter_linked_accounting(): void
    {
        $this->createClass(['code' => 'FLT-LNK', 'accounting_class_id' => $this->accountingLeaf->id]);
        $this->createClass(['code' => 'FLT-UNL']);

        $response = $this->actingAs($this->adminUser)
            ->get(route('management-classes.index', ['accounting_link' => 'linked']));

        $response->assertStatus(200);
    }
}
