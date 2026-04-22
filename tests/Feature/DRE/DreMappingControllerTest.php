<?php

namespace Tests\Feature\DRE;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DreMappingControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private DreManagementLine $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        // Reaproveita uma linha gerencial já semeada (L01 = Faturamento Bruto).
        $this->line = DreManagementLine::where('code', 'L01')->firstOrFail();
    }

    // -----------------------------------------------------------------
    // Autorização
    // -----------------------------------------------------------------

    public function test_user_without_permission_gets_403_on_index(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('dre.mappings.index'))
            ->assertStatus(403);
    }

    public function test_support_can_view_but_cannot_create(): void
    {
        $this->actingAs($this->supportUser)
            ->get(route('dre.mappings.index'))
            ->assertOk();

        $account = ChartOfAccount::factory()->analytical()->revenue()->create();
        $this->actingAs($this->supportUser)
            ->post(route('dre.mappings.store'), [
                'chart_of_account_id' => $account->id,
                'dre_management_line_id' => $this->line->id,
                'effective_from' => '2026-01-01',
            ])
            ->assertStatus(403);
    }

    // -----------------------------------------------------------------
    // Validação: conta analítica obrigatória
    // -----------------------------------------------------------------

    public function test_cannot_map_synthetic_account(): void
    {
        $synthetic = ChartOfAccount::factory()->synthetic()->create();

        $this->actingAs($this->adminUser)
            ->post(route('dre.mappings.store'), [
                'chart_of_account_id' => $synthetic->id,
                'dre_management_line_id' => $this->line->id,
                'effective_from' => '2026-01-01',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('chart_of_account_id');

        $this->assertDatabaseMissing('dre_mappings', [
            'chart_of_account_id' => $synthetic->id,
        ]);
    }

    public function test_can_create_mapping_for_analytical_account(): void
    {
        $account = ChartOfAccount::factory()->analytical()->revenue()->create();

        $this->actingAs($this->adminUser)
            ->post(route('dre.mappings.store'), [
                'chart_of_account_id' => $account->id,
                'dre_management_line_id' => $this->line->id,
                'effective_from' => '2026-01-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dre_mappings', [
            'chart_of_account_id' => $account->id,
            'dre_management_line_id' => $this->line->id,
        ]);
    }

    // -----------------------------------------------------------------
    // Duplicata temporal
    // -----------------------------------------------------------------

    public function test_rejects_overlapping_mapping_for_same_account_and_cc(): void
    {
        $account = ChartOfAccount::factory()->analytical()->revenue()->create();
        $cc = CostCenter::factory()->create();

        // Mapping ativo 2026-01..2026-12.
        DreMapping::factory()
            ->for($account, 'chartOfAccount')
            ->for($this->line, 'dreManagementLine')
            ->create([
                'cost_center_id' => $cc->id,
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'created_by_user_id' => $this->adminUser->id,
            ]);

        // Novo mapping se sobrepõe ao anterior.
        $this->actingAs($this->adminUser)
            ->post(route('dre.mappings.store'), [
                'chart_of_account_id' => $account->id,
                'cost_center_id' => $cc->id,
                'dre_management_line_id' => $this->line->id,
                'effective_from' => '2026-06-01',
                'effective_to' => '2027-05-31',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('effective_from');
    }

    public function test_accepts_mapping_with_different_cc_for_same_account(): void
    {
        $account = ChartOfAccount::factory()->analytical()->revenue()->create();
        $cc1 = CostCenter::factory()->create();
        $cc2 = CostCenter::factory()->create();

        DreMapping::factory()
            ->for($account, 'chartOfAccount')
            ->for($this->line, 'dreManagementLine')
            ->create([
                'cost_center_id' => $cc1->id,
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'created_by_user_id' => $this->adminUser->id,
            ]);

        $this->actingAs($this->adminUser)
            ->post(route('dre.mappings.store'), [
                'chart_of_account_id' => $account->id,
                'cost_center_id' => $cc2->id,
                'dre_management_line_id' => $this->line->id,
                'effective_from' => '2026-01-01',
            ])
            ->assertRedirect();

        $this->assertSame(
            2,
            DreMapping::where('chart_of_account_id', $account->id)->count()
        );
    }

    // -----------------------------------------------------------------
    // Bulk assign
    // -----------------------------------------------------------------

    public function test_bulk_assign_creates_50_mappings_in_one_go(): void
    {
        $accounts = ChartOfAccount::factory()
            ->count(50)
            ->analytical()
            ->create(['account_group' => 4]);

        $ids = $accounts->pluck('id')->all();

        $this->actingAs($this->adminUser)
            ->post(route('dre.mappings.bulk'), [
                'account_ids' => $ids,
                'cost_center_id' => null,
                'dre_management_line_id' => $this->line->id,
                'effective_from' => '2026-01-01',
            ])
            ->assertRedirect();

        $this->assertSame(
            50,
            DreMapping::whereIn('chart_of_account_id', $ids)->count()
        );
    }

    public function test_bulk_skips_synthetic_accounts(): void
    {
        $analytical = ChartOfAccount::factory()->analytical()->create(['account_group' => 4]);
        $synthetic = ChartOfAccount::factory()->synthetic()->create(['account_group' => 4]);

        $this->actingAs($this->adminUser)
            ->post(route('dre.mappings.bulk'), [
                'account_ids' => [$analytical->id, $synthetic->id],
                'dre_management_line_id' => $this->line->id,
                'effective_from' => '2026-01-01',
            ])
            ->assertRedirect();

        $this->assertSame(1, DreMapping::count());
        $this->assertDatabaseHas('dre_mappings', ['chart_of_account_id' => $analytical->id]);
        $this->assertDatabaseMissing('dre_mappings', ['chart_of_account_id' => $synthetic->id]);
    }

    // -----------------------------------------------------------------
    // findUnmappedAccounts
    // -----------------------------------------------------------------

    public function test_unmapped_filters_to_result_groups_only(): void
    {
        // Conta de grupo 1 (ativo) — NÃO deve aparecer.
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.1.ASSET',
            'account_group' => 1,
            'is_active' => true,
        ]);
        // Conta de grupo 2 (passivo) — NÃO deve aparecer.
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.2.LIAB',
            'account_group' => 2,
            'is_active' => true,
        ]);
        // Conta de grupo 3 analítica sem mapping — deve aparecer.
        $result3 = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.3.REV',
            'account_group' => 3,
            'is_active' => true,
        ]);
        // Conta de grupo 4 analítica COM mapping vigente — NÃO deve aparecer.
        $result4Mapped = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.4.EXPMAPPED',
            'account_group' => 4,
            'is_active' => true,
        ]);
        DreMapping::factory()
            ->for($result4Mapped, 'chartOfAccount')
            ->for($this->line, 'dreManagementLine')
            ->create([
                'effective_from' => '2000-01-01',
                'effective_to' => null,
                'created_by_user_id' => $this->adminUser->id,
            ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('dre.mappings.unmapped'));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('DRE/Mappings/Unmapped')
            ->has('accounts')
        );

        // Carrega os dados crus e assegura as regras.
        $ids = $response->viewData('page')['props']['accounts'];
        $codes = collect($ids)->pluck('code')->all();

        $this->assertContains('TST.3.REV', $codes);
        $this->assertNotContains('TST.1.ASSET', $codes);
        $this->assertNotContains('TST.2.LIAB', $codes);
        $this->assertNotContains('TST.4.EXPMAPPED', $codes);
    }

    // -----------------------------------------------------------------
    // Delete
    // -----------------------------------------------------------------

    public function test_delete_mapping(): void
    {
        $account = ChartOfAccount::factory()->analytical()->revenue()->create();
        $mapping = DreMapping::factory()
            ->for($account, 'chartOfAccount')
            ->for($this->line, 'dreManagementLine')
            ->create(['created_by_user_id' => $this->adminUser->id]);

        $this->actingAs($this->adminUser)
            ->delete(route('dre.mappings.destroy', $mapping->id))
            ->assertRedirect();

        $this->assertNotNull($mapping->fresh()->deleted_at);
    }

    // -----------------------------------------------------------------
    // search-accounts (autocomplete)
    // -----------------------------------------------------------------

    public function test_search_accounts_returns_analytical_only(): void
    {
        ChartOfAccount::factory()->analytical()->create([
            'code' => 'SEARCH.1.01.00001',
            'name' => 'Conta Analítica Teste',
            'account_group' => 3,
        ]);
        ChartOfAccount::factory()->synthetic()->create([
            'code' => 'SEARCH.1',
            'name' => 'Conta Sintética Teste',
            'account_group' => 3,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('dre.mappings.search-accounts', ['q' => 'SEARCH']));

        $response->assertOk();
        $results = $response->json('results');

        $codes = collect($results)->pluck('code')->all();
        $this->assertContains('SEARCH.1.01.00001', $codes);
        $this->assertNotContains('SEARCH.1', $codes);
    }
}
