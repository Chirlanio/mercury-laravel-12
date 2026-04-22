<?php

namespace Tests\Feature\DRE;

use App\Enums\Role;
use App\Models\ChartOfAccount;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DreManagementLineControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_user_without_permission_is_blocked_on_index(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('dre.management-lines.index'))
            ->assertStatus(403);
    }

    public function test_support_can_view_but_cannot_manage(): void
    {
        $this->actingAs($this->supportUser)
            ->get(route('dre.management-lines.index'))
            ->assertOk();

        $this->actingAs($this->supportUser)
            ->post(route('dre.management-lines.store'), $this->validAttributes())
            ->assertStatus(403);
    }

    public function test_admin_can_view_and_manage(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('dre.management-lines.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DRE/ManagementLines/Index')
                ->has('lines')
                ->where('can.manage', true)
            );
    }

    // -----------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------

    public function test_create_line(): void
    {
        $attrs = $this->validAttributes();

        $this->actingAs($this->adminUser)
            ->post(route('dre.management-lines.store'), $attrs)
            ->assertRedirect(route('dre.management-lines.index'));

        $this->assertDatabaseHas('dre_management_lines', [
            'code' => $attrs['code'],
            'level_1' => $attrs['level_1'],
            'is_subtotal' => false,
        ]);
    }

    public function test_update_line(): void
    {
        $line = DreManagementLine::factory()->create(['code' => 'TST100']);

        $this->actingAs($this->adminUser)
            ->put(route('dre.management-lines.update', $line->id), [
                'level_1' => '(-) Atualizado',
                'sort_order' => 999,
                'nature' => DreManagementLine::NATURE_EXPENSE,
            ])
            ->assertRedirect(route('dre.management-lines.index'));

        $this->assertDatabaseHas('dre_management_lines', [
            'id' => $line->id,
            'level_1' => '(-) Atualizado',
            'sort_order' => 999,
        ]);
    }

    public function test_delete_line_without_mappings(): void
    {
        $line = DreManagementLine::factory()->create(['code' => 'TST101']);

        $this->actingAs($this->adminUser)
            ->delete(route('dre.management-lines.destroy', $line->id))
            ->assertRedirect(route('dre.management-lines.index'));

        $this->assertNotNull($line->fresh()->deleted_at);
    }

    public function test_cannot_delete_line_with_active_mapping(): void
    {
        $line = DreManagementLine::factory()->create(['code' => 'TST102']);
        $account = ChartOfAccount::factory()->analytical()->create();

        DreMapping::factory()
            ->for($account, 'chartOfAccount')
            ->for($line, 'dreManagementLine')
            ->create(['created_by_user_id' => $this->adminUser->id]);

        $this->actingAs($this->adminUser)
            ->delete(route('dre.management-lines.destroy', $line->id))
            ->assertStatus(302)
            ->assertSessionHasErrors('dre_management_line');

        $this->assertNull($line->fresh()->deleted_at);
    }

    // -----------------------------------------------------------------
    // Reorder
    // -----------------------------------------------------------------

    public function test_reorder_recalculates_sort_order(): void
    {
        $a = DreManagementLine::factory()->create(['code' => 'TST201', 'sort_order' => 500]);
        $b = DreManagementLine::factory()->create(['code' => 'TST202', 'sort_order' => 501]);
        $c = DreManagementLine::factory()->create(['code' => 'TST203', 'sort_order' => 502]);

        $this->actingAs($this->adminUser)
            ->post(route('dre.management-lines.reorder'), [
                'ids' => [$c->id, $a->id, $b->id],
            ])
            ->assertRedirect(route('dre.management-lines.index'));

        $this->assertSame(1, $c->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);
    }

    // -----------------------------------------------------------------
    // Subtotal conflict rule
    // -----------------------------------------------------------------

    public function test_cannot_create_two_subtotals_at_same_sort_order(): void
    {
        DreManagementLine::factory()->subtotal(5)->create([
            'code' => 'SUB1',
            'sort_order' => 100,
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('dre.management-lines.store'), [
                'code' => 'SUB2',
                'sort_order' => 100,
                'is_subtotal' => true,
                'accumulate_until_sort_order' => 99,
                'level_1' => '(=) Outro subtotal',
                'nature' => DreManagementLine::NATURE_SUBTOTAL,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('sort_order');
    }

    public function test_analytical_and_subtotal_can_share_sort_order(): void
    {
        // Caso Headcount + EBITDA no sort_order=13.
        DreManagementLine::factory()->create([
            'code' => 'ANAL1',
            'sort_order' => 77,
            'is_subtotal' => false,
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('dre.management-lines.store'), [
                'code' => 'SUB77',
                'sort_order' => 77,
                'is_subtotal' => true,
                'accumulate_until_sort_order' => 77,
                'level_1' => '(=) Subtotal',
                'nature' => DreManagementLine::NATURE_SUBTOTAL,
            ])
            ->assertRedirect(route('dre.management-lines.index'));

        $this->assertDatabaseHas('dre_management_lines', ['code' => 'SUB77']);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function validAttributes(array $overrides = []): array
    {
        return array_merge([
            'code' => 'TST'.mt_rand(1000, 9999),
            'sort_order' => mt_rand(500, 999),
            'is_subtotal' => false,
            'level_1' => '(-) Linha de teste',
            'nature' => DreManagementLine::NATURE_EXPENSE,
        ], $overrides);
    }
}
