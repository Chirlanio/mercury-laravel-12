<?php

namespace Tests\Feature\DamagedProducts;

use App\Enums\FootSide;
use App\Enums\Permission;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use App\Models\DamagedProduct;
use App\Models\DamageType;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TenantPlan;
use App\Models\User;
use App\Services\DamagedProductMatchingService;
use App\Services\DamagedProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DamagedProductControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected int $storeAId;
    protected int $storeBId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->seedDamagedProductPermissions();
        $this->enableModuleForTenants();

        $this->storeAId = $this->createTestStore('Z421');
        $this->storeBId = $this->createTestStore('Z422');
    }

    protected function seedDamagedProductPermissions(): void
    {
        $perms = collect(Permission::cases())
            ->filter(fn ($p) => str_starts_with($p->value, 'damaged_products.'));

        $permIds = [];
        foreach ($perms as $perm) {
            $cp = CentralPermission::firstOrCreate(
                ['slug' => $perm->value],
                [
                    'label' => $perm->label(),
                    'description' => $perm->description(),
                    'group' => 'damaged_products',
                    'is_active' => true,
                ]
            );
            $permIds[] = $cp->id;
        }

        $adminRole = CentralRole::where('name', 'admin')->first();
        $adminRole?->permissions()->syncWithoutDetaching($permIds);

        $userRole = CentralRole::where('name', 'user')->first();
        if ($userRole) {
            $userPermIds = CentralPermission::whereIn('slug', [
                'damaged_products.view',
                'damaged_products.create',
                'damaged_products.edit',
            ])->pluck('id')->toArray();
            $userRole->permissions()->syncWithoutDetaching($userPermIds);
        }
    }

    /**
     * Habilita o módulo damaged_products em todos os planos para que
     * CheckTenantModule middleware permita as rotas.
     */
    protected function enableModuleForTenants(): void
    {
        $plans = TenantPlan::all();
        foreach ($plans as $plan) {
            TenantModule::firstOrCreate(
                ['plan_id' => $plan->id, 'module_slug' => 'damaged_products'],
                ['is_enabled' => true]
            );
        }
    }

    protected function makeProduct(): DamagedProduct
    {
        return app(DamagedProductService::class)->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'CTRL-' . uniqid(),
            'is_mismatched' => true,
            'mismatched_foot' => FootSide::LEFT->value,
            'mismatched_actual_size' => '38',
            'mismatched_expected_size' => '39',
        ], $this->adminUser);
    }

    // ==================================================================
    // Index
    // ==================================================================

    public function test_index_returns_inertia_response_with_props(): void
    {
        $this->makeProduct();

        $response = $this->actingAs($this->adminUser)
            ->get(route('damaged-products.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('DamagedProducts/Index')
            ->has('items.data')
            ->has('statistics')
            ->has('selects.stores')
            ->has('selects.damageTypes')
            ->has('permissions')
            ->where('isStoreScoped', false)
        );
    }

    public function test_index_scopes_by_user_store_when_no_manage(): void
    {
        // Cria 2 produtos em lojas diferentes
        $this->makeProduct();
        app(DamagedProductService::class)->create([
            'store_id' => $this->storeBId,
            'product_reference' => 'CTRL-OTHER-' . uniqid(),
            'is_mismatched' => true,
            'mismatched_foot' => FootSide::LEFT->value,
            'mismatched_actual_size' => '38',
            'mismatched_expected_size' => '39',
        ], $this->adminUser);

        // User é da loja Z421 e não tem MANAGE
        $this->regularUser->update(['store_id' => 'Z421']);

        $response = $this->actingAs($this->regularUser)
            ->get(route('damaged-products.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('isStoreScoped', true)
            ->where('scopedStoreId', $this->storeAId)
            ->where('items.data', fn ($items) => count($items) === 1)
        );
    }

    // ==================================================================
    // Store
    // ==================================================================

    public function test_store_creates_product(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('damaged-products.store'), [
                'store_id' => $this->storeAId,
                'product_reference' => 'NEW-001',
                'is_mismatched' => true,
                'mismatched_foot' => FootSide::LEFT->value,
                'mismatched_actual_size' => '38',
                'mismatched_expected_size' => '39',
            ]);

        $response->assertRedirect(route('damaged-products.index'));
        $this->assertDatabaseHas('damaged_products', [
            'product_reference' => 'NEW-001',
            'store_id' => $this->storeAId,
            'is_mismatched' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    public function test_store_blocks_other_store_for_scoped_user(): void
    {
        $this->regularUser->update(['store_id' => 'Z421']);

        // Tenta criar pra loja B (não é a do user)
        $response = $this->actingAs($this->regularUser)
            ->post(route('damaged-products.store'), [
                'store_id' => $this->storeBId,
                'product_reference' => 'BLOCKED',
                'is_mismatched' => true,
                'mismatched_foot' => FootSide::LEFT->value,
                'mismatched_actual_size' => '38',
                'mismatched_expected_size' => '39',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('damaged_products', [
            'product_reference' => 'BLOCKED',
        ]);
    }

    // ==================================================================
    // Show
    // ==================================================================

    public function test_show_returns_json_with_history(): void
    {
        $product = $this->makeProduct();

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('damaged-products.show', $product->ulid));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'item' => ['id', 'ulid', 'store', 'product_reference', 'status', 'photos'],
            'history' => [],
        ]);
    }

    public function test_show_blocks_other_store_for_scoped_user(): void
    {
        $product = $this->makeProduct(); // store A
        $this->regularUser->update(['store_id' => 'Z422']); // user da B

        $response = $this->actingAs($this->regularUser)
            ->getJson(route('damaged-products.show', $product->ulid));

        $response->assertStatus(403);
    }

    // ==================================================================
    // Destroy (cancel)
    // ==================================================================

    public function test_destroy_requires_reason(): void
    {
        $product = $this->makeProduct();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('damaged-products.destroy', $product->ulid));

        $response->assertSessionHasErrors('reason');
    }

    public function test_destroy_cancels_with_reason(): void
    {
        $product = $this->makeProduct();

        $response = $this->actingAs($this->adminUser)
            ->delete(route('damaged-products.destroy', $product->ulid), [
                'reason' => 'Cancelando teste',
            ]);

        $response->assertRedirect(route('damaged-products.index'));
        $product->refresh();
        $this->assertSame('cancelled', $product->status->value);
        $this->assertNotNull($product->cancelled_at);
    }

    // ==================================================================
    // Lookups
    // ==================================================================

    public function test_search_products_returns_empty_for_short_query(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('damaged-products.lookup.products', ['q' => 'a']));

        $response->assertStatus(200);
        $response->assertJson(['products' => []]);
    }

    public function test_statistics_returns_zeroed_json_when_empty(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('damaged-products.statistics'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total', 'open', 'matched', 'transfer_requested', 'resolved', 'cancelled', 'resolution_rate',
        ]);
        $response->assertJson(['total' => 0]);
    }

    // ==================================================================
    // Match accept (cria Transfer com damage_match)
    // ==================================================================

    public function test_accept_match_creates_transfer_via_http(): void
    {
        // Setup do par com match
        $a = $this->makeProduct();
        $b = app(DamagedProductService::class)->create([
            'store_id' => $this->storeBId,
            'product_reference' => $a->product_reference,
            'is_mismatched' => true,
            'mismatched_foot' => FootSide::RIGHT->value,
            'mismatched_actual_size' => '39',
            'mismatched_expected_size' => '38',
        ], $this->adminUser);

        $match = app(DamagedProductMatchingService::class)->findMatchesFor($a)->first();
        $this->assertNotNull($match);

        $response = $this->actingAs($this->adminUser)
            ->post(route('damaged-products.matches.accept', $match->id), [
                'invoice_number' => 'NF-CTRL-001',
            ]);

        $response->assertRedirect();

        $match->refresh();
        $this->assertSame('accepted', $match->status->value);
        $this->assertNotNull($match->transfer_id);

        $this->assertDatabaseHas('transfers', [
            'id' => $match->transfer_id,
            'transfer_type' => 'damage_match',
            'invoice_number' => 'NF-CTRL-001',
        ]);
    }
}
