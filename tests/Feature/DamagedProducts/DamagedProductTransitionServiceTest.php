<?php

namespace Tests\Feature\DamagedProducts;

use App\Enums\DamagedProductStatus;
use App\Enums\FootSide;
use App\Enums\Permission;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use App\Models\DamagedProduct;
use App\Models\DamagedProductStatusHistory;
use App\Services\DamagedProductService;
use App\Services\DamagedProductTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DamagedProductTransitionServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected DamagedProductService $service;
    protected DamagedProductTransitionService $transitions;
    protected int $storeAId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->seedDamagedProductPermissions();

        $this->storeAId = $this->createTestStore('Z421');
        $this->service = app(DamagedProductService::class);
        $this->transitions = app(DamagedProductTransitionService::class);
    }

    /**
     * Garante que as 8 permissions damaged_products.* existam e estejam
     * atribuídas ao role admin. As permissions já são seedadas pelo migration
     * 2026_04_27_800001 — usar firstOrCreate pra ser idempotente.
     */
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
        if ($adminRole) {
            $adminRole->permissions()->syncWithoutDetaching($permIds);
        }
    }

    protected function makeOpenProduct(): DamagedProduct
    {
        return $this->service->create([
            'store_id' => $this->storeAId,
            'product_reference' => 'TRANS-' . uniqid(),
            'is_mismatched' => true,
            'mismatched_foot' => FootSide::LEFT->value,
            'mismatched_actual_size' => '38',
            'mismatched_expected_size' => '39',
        ], $this->adminUser);
    }

    // ==================================================================
    // Validação de transições
    // ==================================================================

    public function test_blocks_invalid_transition(): void
    {
        $product = $this->makeOpenProduct();

        $this->expectException(ValidationException::class);
        // open → transfer_requested não é permitido (precisa passar por matched)
        $this->transitions->transition($product, DamagedProductStatus::TRANSFER_REQUESTED, $this->adminUser);
    }

    public function test_blocks_transition_from_terminal_state(): void
    {
        $product = $this->makeOpenProduct();
        $product->update(['status' => DamagedProductStatus::RESOLVED->value]);

        $this->expectException(ValidationException::class);
        $this->transitions->transition($product->fresh(), DamagedProductStatus::OPEN, $this->adminUser);
    }

    public function test_cancel_requires_note(): void
    {
        $product = $this->makeOpenProduct();

        $this->expectException(ValidationException::class);
        $this->transitions->transition($product, DamagedProductStatus::CANCELLED, $this->adminUser, '');
    }

    public function test_cancel_with_note_succeeds_and_writes_metadata(): void
    {
        $product = $this->makeOpenProduct();

        $cancelled = $this->transitions->transition(
            $product,
            DamagedProductStatus::CANCELLED,
            $this->adminUser,
            'Reembolsado ao cliente'
        );

        $this->assertSame(DamagedProductStatus::CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame($this->adminUser->id, $cancelled->cancelled_by_user_id);
        $this->assertSame('Reembolsado ao cliente', $cancelled->cancel_reason);
    }

    public function test_resolve_writes_resolved_at(): void
    {
        $product = $this->makeOpenProduct();
        // open → matched → resolved (path válido)
        $product->update(['status' => DamagedProductStatus::MATCHED->value]);

        $resolved = $this->transitions->transition(
            $product->fresh(),
            DamagedProductStatus::RESOLVED,
            $this->adminUser
        );

        $this->assertSame(DamagedProductStatus::RESOLVED, $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
    }

    // ==================================================================
    // Status history
    // ==================================================================

    public function test_creates_status_history_entry(): void
    {
        $product = $this->makeOpenProduct();

        $this->transitions->transition(
            $product,
            DamagedProductStatus::CANCELLED,
            $this->adminUser,
            'Test cancel'
        );

        $history = DamagedProductStatusHistory::where('damaged_product_id', $product->id)->first();
        $this->assertNotNull($history);
        $this->assertSame(DamagedProductStatus::OPEN->value, $history->from_status);
        $this->assertSame(DamagedProductStatus::CANCELLED->value, $history->to_status);
        $this->assertSame('Test cancel', $history->note);
        $this->assertSame($this->adminUser->id, $history->actor_user_id);
    }

    // ==================================================================
    // Authorization (sem MANAGE — testando regular user)
    // ==================================================================

    public function test_regular_user_cannot_cancel_without_delete_permission(): void
    {
        $product = $this->makeOpenProduct();

        $this->expectException(ValidationException::class);
        $this->transitions->transition(
            $product,
            DamagedProductStatus::CANCELLED,
            $this->regularUser,
            'Tentativa'
        );
    }

    public function test_admin_with_manage_bypasses_all_permission_checks(): void
    {
        $product = $this->makeOpenProduct();

        // Admin tem MANAGE_DAMAGED_PRODUCTS pela atribuição via Role enum
        $resolved = $this->transitions->transition(
            $product,
            DamagedProductStatus::RESOLVED,
            $this->adminUser
        );

        $this->assertSame(DamagedProductStatus::RESOLVED, $resolved->status);
    }
}
