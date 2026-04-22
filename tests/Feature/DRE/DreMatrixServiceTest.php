<?php

namespace Tests\Feature\DRE;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreActual;
use App\Models\DreBudget;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use App\Models\Network;
use App\Models\Store;
use App\Models\User;
use App\Services\DRE\Contracts\ClosedPeriodReader;
use App\Services\DRE\DreMappingResolver;
use App\Services\DRE\DreMatrixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes feature do `DreMatrixService` — exercitam o pipeline completo
 * contra DB em memória (SQLite). Cobre os cenários do playbook prompt 6.
 */
class DreMatrixServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DreMappingResolver::resetCache();
    }

    // -----------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------

    public function test_end_to_end_simple_scenario(): void
    {
        $revenueLine = DreManagementLine::where('code', 'L01')->firstOrFail();
        $revenueAccount = ChartOfAccount::factory()->revenue()->analytical()->create(['code' => 'TST.REV.01']);

        $this->mapAccount($revenueAccount, null, $revenueLine);

        DreActual::factory()->for($revenueAccount, 'chartOfAccount')->create([
            'entry_date' => '2026-03-15',
            'amount' => 1000.00,
        ]);

        $matrix = $this->service()->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'scope' => 'general',
        ]);

        $rev = collect($matrix['lines'])->firstWhere('code', 'L01');
        $this->assertNotNull($rev);
        $this->assertSame(1000.00, (float) $rev['months']['2026-03']['actual']);
    }

    public function test_unmapped_account_falls_into_unclassified(): void
    {
        $account = ChartOfAccount::factory()->revenue()->analytical()->create(['code' => 'TST.UNM.01']);

        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'entry_date' => '2026-03-15',
            'amount' => 500.00,
        ]);

        $matrix = $this->service()->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $l99 = collect($matrix['lines'])->firstWhere('code', 'L99_UNCLASSIFIED');
        $this->assertNotNull($l99, 'L99_UNCLASSIFIED deve aparecer.');
        $this->assertSame(500.00, (float) $l99['totals']['actual']);
    }

    // -----------------------------------------------------------------
    // Precedência específico x coringa (integração com resolver)
    // -----------------------------------------------------------------

    public function test_wildcard_mapping_captures_when_no_specific_exists(): void
    {
        $line = DreManagementLine::where('code', 'L10')->firstOrFail();
        $account = ChartOfAccount::factory()->analytical()->create(['code' => 'TST.WC.01', 'account_group' => 4]);
        $cc = CostCenter::factory()->create();

        $this->mapAccount($account, null, $line); // coringa

        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'cost_center_id' => $cc->id,
            'entry_date' => '2026-04-10',
            'amount' => -200.00,
        ]);

        $matrix = $this->service()->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $row = collect($matrix['lines'])->firstWhere('id', $line->id);
        $this->assertSame(-200.00, (float) $row['months']['2026-04']['actual']);
    }

    public function test_specific_mapping_wins_over_wildcard(): void
    {
        $wildcardLine = DreManagementLine::where('code', 'L10')->firstOrFail();
        $specificLine = DreManagementLine::where('code', 'L13')->firstOrFail();
        $account = ChartOfAccount::factory()->analytical()->create(['code' => 'TST.SP.01', 'account_group' => 4]);
        $cc = CostCenter::factory()->create();

        $this->mapAccount($account, null, $wildcardLine);     // coringa
        $this->mapAccount($account, $cc, $specificLine);       // específico

        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'cost_center_id' => $cc->id,
            'entry_date' => '2026-05-10',
            'amount' => -300.00,
        ]);

        $matrix = $this->service()->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $specificRow = collect($matrix['lines'])->firstWhere('id', $specificLine->id);
        $wildcardRow = collect($matrix['lines'])->firstWhere('id', $wildcardLine->id);

        $this->assertSame(-300.00, (float) $specificRow['months']['2026-05']['actual']);
        $this->assertSame(0.0, (float) ($wildcardRow['months']['2026-05']['actual'] ?? 0.0));
    }

    // -----------------------------------------------------------------
    // Filtros de escopo
    // -----------------------------------------------------------------

    public function test_store_filter_isolates_entries(): void
    {
        $line = DreManagementLine::where('code', 'L10')->firstOrFail();
        $account = ChartOfAccount::factory()->analytical()->create(['code' => 'TST.ST.01', 'account_group' => 4]);
        $this->mapAccount($account, null, $line);

        $s1 = Store::factory()->create();
        $s2 = Store::factory()->create();

        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'store_id' => $s1->id,
            'entry_date' => '2026-02-10',
            'amount' => -100.00,
        ]);
        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'store_id' => $s2->id,
            'entry_date' => '2026-02-10',
            'amount' => -999.00,
        ]);

        $matrix = $this->service()->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'scope' => 'store',
            'store_ids' => [$s1->id],
        ]);

        $row = collect($matrix['lines'])->firstWhere('id', $line->id);
        $this->assertSame(-100.00, (float) $row['months']['2026-02']['actual']);
    }

    public function test_network_scope_aggregates_stores_of_selected_networks(): void
    {
        $line = DreManagementLine::where('code', 'L10')->firstOrFail();
        $account = ChartOfAccount::factory()->analytical()->create(['code' => 'TST.NET.01', 'account_group' => 4]);
        $this->mapAccount($account, null, $line);

        $net1 = Network::create(['nome' => 'Net A', 'type' => 'comercial', 'active' => true]);
        $net2 = Network::create(['nome' => 'Net B', 'type' => 'comercial', 'active' => true]);
        $s1 = Store::factory()->create(['network_id' => $net1->id]);
        $s2 = Store::factory()->create(['network_id' => $net1->id]);
        $s3 = Store::factory()->create(['network_id' => $net2->id]);

        foreach ([$s1->id, $s2->id] as $sid) {
            DreActual::factory()->for($account, 'chartOfAccount')->create([
                'store_id' => $sid,
                'entry_date' => '2026-02-10',
                'amount' => -100.00,
            ]);
        }
        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'store_id' => $s3->id,
            'entry_date' => '2026-02-10',
            'amount' => -999.00,
        ]);

        $matrix = $this->service()->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'scope' => 'network',
            'network_ids' => [$net1->id],
        ]);

        $row = collect($matrix['lines'])->firstWhere('id', $line->id);
        $this->assertSame(-200.00, (float) $row['months']['2026-02']['actual']);
    }

    // -----------------------------------------------------------------
    // Previous year comparativo
    // -----------------------------------------------------------------

    public function test_previous_year_shifts_actuals_to_current_months(): void
    {
        $line = DreManagementLine::where('code', 'L01')->firstOrFail();
        $account = ChartOfAccount::factory()->revenue()->analytical()->create(['code' => 'TST.PY.01']);
        $this->mapAccount($account, null, $line);

        // Current 2026: 1000 em março.
        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'entry_date' => '2026-03-15',
            'amount' => 1000.00,
        ]);
        // Previous 2025: 800 em março.
        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'entry_date' => '2025-03-15',
            'amount' => 800.00,
        ]);

        $matrix = $this->service()->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'compare_previous_year' => true,
        ]);

        $row = collect($matrix['lines'])->firstWhere('id', $line->id);
        $this->assertSame(1000.00, (float) $row['months']['2026-03']['actual']);
        $this->assertSame(800.00, (float) $row['months']['2026-03']['previous_year']);
    }

    // -----------------------------------------------------------------
    // Budget version isolation
    // -----------------------------------------------------------------

    public function test_budget_version_isolates_values(): void
    {
        $line = DreManagementLine::where('code', 'L01')->firstOrFail();
        $account = ChartOfAccount::factory()->revenue()->analytical()->create(['code' => 'TST.BV.01']);
        $this->mapAccount($account, null, $line);

        DreBudget::factory()->for($account, 'chartOfAccount')->create([
            'budget_version' => 'v1',
            'entry_date' => '2026-03-01',
            'amount' => 500.00,
        ]);
        DreBudget::factory()->for($account, 'chartOfAccount')->create([
            'budget_version' => 'v2',
            'entry_date' => '2026-03-01',
            'amount' => 9999.00,
        ]);

        $matrix = $this->service()->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'budget_version' => 'v1',
        ]);

        $row = collect($matrix['lines'])->firstWhere('id', $line->id);
        $this->assertSame(500.00, (float) $row['months']['2026-03']['budget']);
    }

    // -----------------------------------------------------------------
    // include_unclassified
    // -----------------------------------------------------------------

    public function test_include_unclassified_false_removes_ghost_line(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create(['code' => 'TST.IU.01', 'account_group' => 4]);
        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'entry_date' => '2026-03-01',
            'amount' => -50.00,
        ]);

        $matrix = $this->service()->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'include_unclassified' => false,
        ]);

        $l99 = collect($matrix['lines'])->firstWhere('code', 'L99_UNCLASSIFIED');
        $this->assertNull($l99);
    }

    // -----------------------------------------------------------------
    // Subtotals
    // -----------------------------------------------------------------

    public function test_subtotal_aggregates_underlying_analytical_lines(): void
    {
        $rev = DreManagementLine::where('code', 'L01')->firstOrFail();
        $ded = DreManagementLine::where('code', 'L02')->firstOrFail();
        $receitaLiq = DreManagementLine::where('code', 'L03')->firstOrFail();

        $accRev = ChartOfAccount::factory()->revenue()->analytical()->create(['code' => 'TST.SUB.R']);
        $accDed = ChartOfAccount::factory()->analytical()->create(['code' => 'TST.SUB.D', 'account_group' => 3]);

        $this->mapAccount($accRev, null, $rev);
        $this->mapAccount($accDed, null, $ded);

        DreActual::factory()->for($accRev, 'chartOfAccount')->create([
            'entry_date' => '2026-03-01',
            'amount' => 1000.00,
        ]);
        DreActual::factory()->for($accDed, 'chartOfAccount')->create([
            'entry_date' => '2026-03-01',
            'amount' => -100.00,
        ]);

        $matrix = $this->service()->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $row = collect($matrix['lines'])->firstWhere('id', $receitaLiq->id);
        $this->assertSame(900.00, (float) $row['months']['2026-03']['actual']);
        $this->assertTrue((bool) $row['is_subtotal']);
    }

    // -----------------------------------------------------------------
    // Snapshot reader (mock via interface)
    // -----------------------------------------------------------------

    public function test_snapshot_overlay_overrides_live_for_closed_months(): void
    {
        $line = DreManagementLine::where('code', 'L01')->firstOrFail();
        $account = ChartOfAccount::factory()->revenue()->analytical()->create(['code' => 'TST.SNP.01']);
        $this->mapAccount($account, null, $line);

        DreActual::factory()->for($account, 'chartOfAccount')->create([
            'entry_date' => '2026-03-15',
            'amount' => 777.00, // valor live
        ]);

        // Mock: fevereiro-março fechados, snapshot traz valor diferente.
        $reader = new class implements ClosedPeriodReader {
            public function closedYearMonths(array $filter): array
            {
                return ['2026-03'];
            }
            public function readSnapshot(array $filter): array
            {
                // Retorna via ID em runtime — lookup da L01.
                $l01 = \App\Models\DreManagementLine::where('code', 'L01')->firstOrFail();

                return [
                    '2026-03' => [
                        $l01->id => ['actual' => 500.00, 'budget' => 0.0, 'previous_year' => 0.0],
                    ],
                ];
            }
        };

        $service = new DreMatrixService($reader);

        $matrix = $service->matrix([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $row = collect($matrix['lines'])->firstWhere('id', $line->id);
        $this->assertSame(500.00, (float) $row['months']['2026-03']['actual'],
            'Snapshot deveria sobrescrever o valor live.');
    }

    // -----------------------------------------------------------------
    // Drill
    // -----------------------------------------------------------------

    public function test_drill_returns_contributing_accounts(): void
    {
        $line = DreManagementLine::where('code', 'L10')->firstOrFail();
        $acc1 = ChartOfAccount::factory()->analytical()->create(['code' => 'TST.DRL.01', 'account_group' => 4]);
        $acc2 = ChartOfAccount::factory()->analytical()->create(['code' => 'TST.DRL.02', 'account_group' => 4]);
        $this->mapAccount($acc1, null, $line);
        $this->mapAccount($acc2, null, $line);

        DreActual::factory()->for($acc1, 'chartOfAccount')->create([
            'entry_date' => '2026-03-01',
            'amount' => -100.00,
        ]);
        DreActual::factory()->for($acc2, 'chartOfAccount')->create([
            'entry_date' => '2026-03-01',
            'amount' => -250.00,
        ]);

        $drill = $this->service()->drill($line->id, [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'compare_previous_year' => false,
        ]);

        $this->assertCount(2, $drill);

        $byCode = collect($drill)->keyBy(fn ($r) => $r['chart_of_account']['code']);
        $this->assertSame(-100.00, (float) $byCode['TST.DRL.01']['actual']);
        $this->assertSame(-250.00, (float) $byCode['TST.DRL.02']['actual']);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function service(): DreMatrixService
    {
        return app(DreMatrixService::class);
    }

    private function mapAccount(ChartOfAccount $account, ?CostCenter $cc, DreManagementLine $line): DreMapping
    {
        return DreMapping::factory()
            ->for($account, 'chartOfAccount')
            ->for($line, 'dreManagementLine')
            ->create([
                'cost_center_id' => $cc?->id,
                'effective_from' => '2020-01-01',
                'effective_to' => null,
                'created_by_user_id' => User::factory()->create()->id,
            ]);
    }
}
