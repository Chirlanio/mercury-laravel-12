<?php

namespace Tests\Feature\DRE;

use App\Models\ChartOfAccount;
use App\Models\DreActual;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use App\Models\User;
use App\Services\DRE\DreMappingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre a camada HTTP da matriz DRE conforme prompt 7 do playbook.
 */
class DreMatrixControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        DreMappingResolver::resetCache();
    }

    // -----------------------------------------------------------------
    // Autorização
    // -----------------------------------------------------------------

    public function test_unauthenticated_is_redirected_to_login(): void
    {
        $this->get(route('dre.matrix.show', $this->defaultParams()))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_view_dre_gets_403(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('dre.matrix.show', $this->defaultParams()))
            ->assertStatus(403);
    }

    public function test_admin_can_access_and_receives_inertia_shape(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('dre.matrix.show', $this->defaultParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('DRE/Matrix')
                ->has('filters')
                ->has('matrix')
                ->has('matrix.lines')
                ->has('matrix.totals')
                ->has('matrix.generated_at')
                ->has('kpis')
                ->has('availableStores')
                ->has('availableNetworks')
                ->has('availableBudgetVersions')
                ->has('closedPeriods')
            );
    }

    public function test_support_can_view_matrix(): void
    {
        // SUPPORT tem VIEW_DRE mas não MANAGE_DRE_STRUCTURE/MAPPINGS —
        // a matriz é read-only pra eles.
        $this->actingAs($this->supportUser)
            ->get(route('dre.matrix.show', $this->defaultParams()))
            ->assertOk();
    }

    // -----------------------------------------------------------------
    // Validação
    // -----------------------------------------------------------------

    public function test_end_date_before_start_date_returns_422(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('dre.matrix.show', [
                'start_date' => '2026-12-31',
                'end_date' => '2026-01-01',
            ]))
            ->assertStatus(302) // redirect com validation errors
            ->assertSessionHasErrors('end_date');
    }

    public function test_missing_dates_applies_defaults(): void
    {
        // Sidebar dispara GET /dre/matrix sem query params — o FormRequest
        // aplica defaults (ano corrente até hoje) em prepareForValidation()
        // ao invés de redirecionar com erro.
        $this->actingAs($this->adminUser)
            ->get(route('dre.matrix.show', []))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('DRE/Matrix'));
    }

    public function test_invalid_date_format_returns_422(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('dre.matrix.show', [
                'start_date' => '31/01/2026',
                'end_date' => '31/12/2026',
            ]))
            ->assertStatus(302)
            ->assertSessionHasErrors('start_date');
    }

    // -----------------------------------------------------------------
    // Payload real — dados chegam à matriz
    // -----------------------------------------------------------------

    public function test_matrix_includes_actual_values_for_mapped_accounts(): void
    {
        $line = DreManagementLine::where('code', 'L01')->firstOrFail();
        $account = ChartOfAccount::factory()->revenue()->analytical()->create(['code' => 'MTX.CTRL.01']);

        DreMapping::factory()
            ->for($account, 'chartOfAccount')
            ->for($line, 'dreManagementLine')
            ->create([
                'effective_from' => '2020-01-01',
                'effective_to' => null,
                'created_by_user_id' => $this->adminUser->id,
            ]);

        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'entry_date' => '2026-03-10',
            'amount' => 1234.56,
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('dre.matrix.show', $this->defaultParams()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('matrix.lines.0.code', 'L01')
                ->where('matrix.totals.actual', fn ($total) => (float) $total === 1234.56)
            );
    }

    // -----------------------------------------------------------------
    // Drill endpoint
    // -----------------------------------------------------------------

    public function test_drill_returns_json_with_contributors(): void
    {
        $line = DreManagementLine::where('code', 'L01')->firstOrFail();
        $account = ChartOfAccount::factory()->revenue()->analytical()->create(['code' => 'MTX.DRL.01']);

        DreMapping::factory()
            ->for($account, 'chartOfAccount')
            ->for($line, 'dreManagementLine')
            ->create([
                'effective_from' => '2020-01-01',
                'effective_to' => null,
                'created_by_user_id' => $this->adminUser->id,
            ]);

        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'entry_date' => '2026-03-10',
            'amount' => 999.99,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('dre.matrix.drill', array_merge($this->defaultParams(), [
                'line' => $line->id,
            ])));

        $response->assertOk()
            ->assertJsonStructure([
                'line' => ['id', 'code', 'level_1', 'is_subtotal', 'nature'],
                'contributors' => [],
                'filter',
            ]);

        $contributors = $response->json('contributors');
        $this->assertCount(1, $contributors);
        $this->assertSame('MTX.DRL.01', $contributors[0]['chart_of_account']['code']);
        $this->assertSame(999.99, (float) $contributors[0]['actual']);
    }

    public function test_drill_rejects_user_without_view_dre(): void
    {
        $line = DreManagementLine::where('code', 'L01')->firstOrFail();

        $this->actingAs($this->regularUser)
            ->getJson(route('dre.matrix.drill', array_merge($this->defaultParams(), [
                'line' => $line->id,
            ])))
            ->assertStatus(403);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function defaultParams(): array
    {
        return [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ];
    }
}
