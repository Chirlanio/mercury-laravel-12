<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountingClass;
use App\Models\BudgetItem;
use App\Models\BudgetUpload;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre a lixeira administrativa de Budgets (Melhoria 10 do roadmap).
 *
 * Testa:
 *   - /budgets/trash lista apenas uploads soft-deletados
 *   - restore zera deleted_at/by/reason e mantém is_active=false
 *   - forceDelete apaga fisicamente + itens (cascade) — só super_admin
 *   - forceDelete rejeita upload não-deletado
 *   - regular user não acessa nenhuma dessas rotas (sem MANAGE_BUDGETS)
 */
class BudgetTrashTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AccountingClass $ac;

    protected ManagementClass $mc;

    protected ManagementClass $areaDepartment;

    protected CostCenter $cc;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        app(\App\Services\CentralRoleResolver::class)->clearCache();

        $this->superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN->value,
            'access_level_id' => 1,
        ]);

        $this->ac = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail();
        $this->areaDepartment = ManagementClass::where('code', '8.1.01')->firstOrFail();

        $this->cc = CostCenter::create([
            'code' => 'CC-TRASH', 'name' => 'Trash CC',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->mc = ManagementClass::create([
            'code' => 'MC-TRASH', 'name' => 'Trash MC',
            'accepts_entries' => true,
            'parent_id' => $this->areaDepartment->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    protected function makeDeletedUpload(array $overrides = []): BudgetUpload
    {
        $upload = BudgetUpload::create(array_merge([
            'year' => 2026,
            'scope_label' => 'Administrativo',
            'version_label' => '1.0',
            'major_version' => 1,
            'minor_version' => 0,
            'upload_type' => 'novo',
            'area_department_id' => $this->areaDepartment->id,
            'original_filename' => 'orc-trash.xlsx',
            'stored_path' => 'budgets/2026/orc-trash.xlsx',
            'file_size_bytes' => 100,
            'is_active' => false,
            'total_year' => 1200,
            'items_count' => 1,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
            'deleted_at' => now(),
            'deleted_by_user_id' => $this->adminUser->id,
            'deleted_reason' => 'Substituída por nova versão',
        ], $overrides));

        BudgetItem::create([
            'budget_upload_id' => $upload->id,
            'accounting_class_id' => $this->ac->id,
            'management_class_id' => $this->mc->id,
            'cost_center_id' => $this->cc->id,
            'supplier' => 'Fornecedor Trash',
            'year_total' => 1200,
            'month_01_value' => 100, 'month_02_value' => 100, 'month_03_value' => 100,
            'month_04_value' => 100, 'month_05_value' => 100, 'month_06_value' => 100,
            'month_07_value' => 100, 'month_08_value' => 100, 'month_09_value' => 100,
            'month_10_value' => 100, 'month_11_value' => 100, 'month_12_value' => 100,
        ]);

        return $upload;
    }

    public function test_trash_page_lists_only_deleted_uploads(): void
    {
        $deleted = $this->makeDeletedUpload();
        // ativo — não deve aparecer na lixeira
        $active = $this->makeDeletedUpload([
            'scope_label' => 'Operacional',
            'is_active' => true,
            'deleted_at' => null,
            'deleted_by_user_id' => null,
            'deleted_reason' => null,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('budgets.trash'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Budgets/Trash')
            ->has('trashed', 1)
            ->where('trashed.0.id', $deleted->id)
            ->where('trashed.0.deleted_reason', 'Substituída por nova versão'));
    }

    public function test_regular_user_cannot_access_trash(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('budgets.trash'));
        $response->assertStatus(403);
    }

    public function test_restore_clears_deleted_fields_and_keeps_inactive(): void
    {
        $upload = $this->makeDeletedUpload();

        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.restore', $upload->id));

        $response->assertRedirect(route('budgets.trash'));
        $response->assertSessionHas('success');

        $fresh = $upload->fresh();
        $this->assertNull($fresh->deleted_at);
        $this->assertNull($fresh->deleted_by_user_id);
        $this->assertNull($fresh->deleted_reason);
        $this->assertFalse((bool) $fresh->is_active, 'Restore não deve reativar a versão');
    }

    public function test_restore_rejects_non_deleted_upload(): void
    {
        $upload = $this->makeDeletedUpload([
            'is_active' => true,
            'deleted_at' => null,
            'deleted_by_user_id' => null,
            'deleted_reason' => null,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('budgets.restore', $upload->id));

        $response->assertSessionHasErrors('id');
    }

    public function test_regular_user_cannot_restore(): void
    {
        $upload = $this->makeDeletedUpload();

        $response = $this->actingAs($this->regularUser)
            ->post(route('budgets.restore', $upload->id));

        $response->assertStatus(403);
        $this->assertNotNull($upload->fresh()->deleted_at);
    }

    public function test_force_delete_requires_super_admin(): void
    {
        $upload = $this->makeDeletedUpload();

        // ADMIN com MANAGE_BUDGETS mas sem role super_admin → 403
        $response = $this->actingAs($this->adminUser)
            ->delete(route('budgets.force-delete', $upload->id));

        $response->assertStatus(403);
        $this->assertNotNull(BudgetUpload::withoutGlobalScopes()->find($upload->id));
    }

    public function test_super_admin_can_force_delete_deleted_upload(): void
    {
        $upload = $this->makeDeletedUpload();
        $itemCount = BudgetItem::where('budget_upload_id', $upload->id)->count();
        $this->assertGreaterThan(0, $itemCount);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('budgets.force-delete', $upload->id));

        $response->assertRedirect(route('budgets.trash'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('budget_uploads', ['id' => $upload->id]);
        // cascade delete em budget_items via FK
        $this->assertDatabaseMissing('budget_items', ['budget_upload_id' => $upload->id]);
    }

    public function test_force_delete_rejects_non_deleted_upload(): void
    {
        $upload = $this->makeDeletedUpload([
            'is_active' => true,
            'deleted_at' => null,
            'deleted_by_user_id' => null,
            'deleted_reason' => null,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('budgets.force-delete', $upload->id));

        $response->assertSessionHasErrors('id');
        $this->assertDatabaseHas('budget_uploads', ['id' => $upload->id]);
    }

    public function test_regular_user_cannot_force_delete(): void
    {
        $upload = $this->makeDeletedUpload();

        $response = $this->actingAs($this->regularUser)
            ->delete(route('budgets.force-delete', $upload->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('budget_uploads', ['id' => $upload->id]);
    }
}
