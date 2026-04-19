<?php

namespace Tests\Feature;

use App\Enums\AccountingNature;
use App\Enums\DreGroup;
use App\Models\AccountingClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class AccountingClassControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    protected function createClass(array $overrides = []): AccountingClass
    {
        return AccountingClass::create(array_merge([
            'code' => 'TEST-'.uniqid(),
            'name' => 'Conta Teste',
            'nature' => AccountingNature::DEBIT->value,
            'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value,
            'accepts_entries' => true,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ], $overrides));
    }

    public function test_seed_populates_br_chart_on_fresh_tenant(): void
    {
        // O seed roda em migrate --seed. Com RefreshDatabase + sqlite
        // in-memory, a migration de seed executa automaticamente.
        $this->assertGreaterThanOrEqual(40, AccountingClass::count());
        $this->assertDatabaseHas('accounting_classes', ['code' => '3.1.01.001']);
        $this->assertDatabaseHas('accounting_classes', ['code' => '5.2.01']);
        $this->assertDatabaseHas('accounting_classes', ['code' => '7.1.01']);
    }

    public function test_admin_can_view_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('accounting-classes.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('AccountingClasses/Index'));
    }

    public function test_regular_user_cannot_view_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('accounting-classes.index'));
        $response->assertStatus(403);
    }

    public function test_index_filter_by_dre_group(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('accounting-classes.index', ['dre_group' => DreGroup::RECEITA_BRUTA->value]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where(
            'filters.dre_group',
            DreGroup::RECEITA_BRUTA->value
        ));
    }

    public function test_index_filter_only_leaves(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('accounting-classes.index', ['accepts_entries' => 1]));

        $response->assertStatus(200);
    }

    public function test_create_new_class(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('accounting-classes.store'), [
            'code' => 'NEW-100',
            'name' => 'Nova Conta',
            'nature' => AccountingNature::DEBIT->value,
            'dre_group' => DreGroup::DESPESAS_ADMINISTRATIVAS->value,
            'accepts_entries' => true,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('accounting_classes', [
            'code' => 'NEW-100',
            'name' => 'Nova Conta',
            'nature' => 'debit',
            'dre_group' => 'despesas_administrativas',
        ]);
    }

    public function test_create_rejects_duplicate_code(): void
    {
        $this->createClass(['code' => 'DUP-CODE']);

        $response = $this->actingAs($this->adminUser)->post(route('accounting-classes.store'), [
            'code' => 'DUP-CODE',
            'name' => 'Tentativa',
            'nature' => AccountingNature::DEBIT->value,
            'dre_group' => DreGroup::DESPESAS_GERAIS->value,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_create_rejects_parent_that_is_leaf(): void
    {
        $leaf = $this->createClass(['code' => 'LEAF-1', 'accepts_entries' => true]);

        $response = $this->actingAs($this->adminUser)->post(route('accounting-classes.store'), [
            'code' => 'CHILD-1',
            'name' => 'Filha de folha',
            'parent_id' => $leaf->id,
            'nature' => AccountingNature::DEBIT->value,
            'dre_group' => DreGroup::DESPESAS_GERAIS->value,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_create_accepts_parent_that_is_synthetic_group(): void
    {
        $group = $this->createClass(['code' => 'GROUP-1', 'accepts_entries' => false]);

        $response = $this->actingAs($this->adminUser)->post(route('accounting-classes.store'), [
            'code' => 'GROUP-1.01',
            'name' => 'Filha de grupo',
            'parent_id' => $group->id,
            'nature' => AccountingNature::DEBIT->value,
            'dre_group' => DreGroup::DESPESAS_GERAIS->value,
            'accepts_entries' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('accounting_classes', [
            'code' => 'GROUP-1.01',
            'parent_id' => $group->id,
        ]);
    }

    public function test_show_returns_json(): void
    {
        $c = $this->createClass(['code' => 'SHOW-1', 'name' => 'Para Ver']);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('accounting-classes.show', $c->id));

        $response->assertStatus(200);
        $response->assertJsonPath('accountingClass.code', 'SHOW-1');
        $response->assertJsonPath('accountingClass.nature', 'debit');
    }

    public function test_update_blocks_becoming_leaf_with_children(): void
    {
        $parent = $this->createClass(['code' => 'P-UPD', 'accepts_entries' => false]);
        $this->createClass(['code' => 'C-UPD', 'parent_id' => $parent->id, 'accepts_entries' => true]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('accounting-classes.update', $parent->id), [
                'accepts_entries' => true,
            ]);

        $response->assertSessionHasErrors('accepts_entries');
        $this->assertFalse($parent->fresh()->accepts_entries);
    }

    public function test_update_blocks_cycle(): void
    {
        $grandparent = $this->createClass(['code' => 'GP-CY', 'accepts_entries' => false]);
        $parent = $this->createClass(['code' => 'P-CY', 'parent_id' => $grandparent->id, 'accepts_entries' => false]);
        $child = $this->createClass(['code' => 'C-CY', 'parent_id' => $parent->id, 'accepts_entries' => false]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('accounting-classes.update', $grandparent->id), [
                'parent_id' => $child->id,
            ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_delete_with_reason(): void
    {
        $c = $this->createClass(['code' => 'DEL-1']);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('accounting-classes.destroy', $c->id), [
                'deleted_reason' => 'Motivo válido',
            ]);

        $response->assertRedirect();
        $this->assertNotNull($c->fresh()->deleted_at);
    }

    public function test_delete_blocked_when_has_children(): void
    {
        $parent = $this->createClass(['code' => 'DEL-P', 'accepts_entries' => false]);
        $this->createClass(['code' => 'DEL-CH', 'parent_id' => $parent->id]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('accounting-classes.destroy', $parent->id), [
                'deleted_reason' => 'Tentativa',
            ]);

        $response->assertSessionHasErrors();
        $this->assertNull($parent->fresh()->deleted_at);
    }

    public function test_delete_requires_reason_min_3_chars(): void
    {
        $c = $this->createClass(['code' => 'DEL-RES']);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('accounting-classes.destroy', $c->id), [
                'deleted_reason' => 'a',
            ]);

        $response->assertSessionHasErrors('deleted_reason');
        $this->assertNull($c->fresh()->deleted_at);
    }

    public function test_statistics_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('accounting-classes.statistics'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total', 'active', 'inactive', 'leaves', 'synthetic_groups', 'by_dre_group',
        ]);
        $this->assertGreaterThanOrEqual(40, $response->json('total'));
    }

    public function test_tree_endpoint_returns_hierarchy(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('accounting-classes.tree'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['tree']);

        // A árvore deve ter raízes (3.1, 3.2, 4.1, 5.1, 5.2, 5.3, 5.4, 5.5, 6.1, 6.2, 7.1)
        $this->assertGreaterThanOrEqual(10, count($response->json('tree')));

        // Raiz 3.1 deve ter filhos
        $receitaBruta = collect($response->json('tree'))->firstWhere('code', '3.1');
        $this->assertNotNull($receitaBruta);
        $this->assertNotEmpty($receitaBruta['children']);
    }
}
