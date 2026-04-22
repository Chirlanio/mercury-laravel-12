<?php

namespace Tests\Feature\DRE;

use App\Enums\AccountGroup;
use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreActual;
use App\Models\DreBudget;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use App\Models\Store;
use Carbon\Carbon;
use Database\Seeders\DreManagementLineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Prompt #2 do DRE — camada de dados executiva.
 *
 * Cobre:
 *   - Seeder com as 20 linhas da DRE gerencial (ordens 1..19 com
 *     empate no sort_order 13 entre Headcount e EBITDA).
 *   - 7 subtotais (L03, L05, L07, L09, L14/EBITDA, L18, L20).
 *   - `accumulate_until_sort_order` conforme regra DAX do Power BI.
 *   - Enums AccountType (synthetic/analytical) e AccountGroup (1..5).
 *   - Coluna `type` em chart_of_accounts + backfill via accepts_entries.
 *   - Scopes novos (ChartOfAccount: byGroup, childrenOf, analytical;
 *     DreActual/DreBudget: forPeriod, forUnit, forCostCenter;
 *     DreMapping: effectiveAt com Carbon).
 *   - Factory state `atLevel()`.
 *   - Idempotência do seeder.
 */
class PromptTwoDataLayerTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Seeder das 20 linhas
    // -----------------------------------------------------------------

    public function test_seeder_produces_20_executive_rows_plus_unclassified(): void
    {
        // 20 linhas executivas (L01..L20, sort_order 1..19) + L99_UNCLASSIFIED = 21.
        $this->assertSame(21, DreManagementLine::count());
        $this->assertSame(20, DreManagementLine::where('code', '!=', DreManagementLine::UNCLASSIFIED_CODE)->count());
    }

    public function test_seeder_produces_7_subtotals(): void
    {
        // Ordens de subtotal: 3, 5, 7, 9, 13 (EBITDA), 17, 19.
        // L99_UNCLASSIFIED não é subtotal.
        $this->assertSame(7, DreManagementLine::where('is_subtotal', true)->count());
    }

    public function test_unclassified_ghost_line_exists(): void
    {
        $ghost = DreManagementLine::where('code', DreManagementLine::UNCLASSIFIED_CODE)->first();

        $this->assertNotNull($ghost, 'L99_UNCLASSIFIED deve estar semeada para o DreMappingResolver usar como fallback.');
        $this->assertSame(9990, $ghost->sort_order);
        $this->assertFalse((bool) $ghost->is_subtotal);
        $this->assertSame('(!) Não classificado', $ghost->level_1);
        $this->assertTrue($ghost->isUnclassified());
    }

    public function test_seeder_has_two_rows_at_sort_order_13(): void
    {
        $rows = DreManagementLine::where('sort_order', 13)
            ->orderBy('is_subtotal')
            ->get();

        $this->assertCount(2, $rows);

        // Analítica primeiro (is_subtotal=false), depois subtotal.
        $this->assertFalse($rows[0]->is_subtotal);
        $this->assertSame('(-) Headcount', $rows[0]->level_1);

        $this->assertTrue($rows[1]->is_subtotal);
        $this->assertSame('(=) EBITDA', $rows[1]->level_1);
    }

    public function test_seeder_subtotals_accumulate_follows_dax(): void
    {
        $cases = [
            ['code' => 'L03', 'accumulate' => 2,  'label' => '(=) Faturamento Líquido'],
            ['code' => 'L05', 'accumulate' => 4,  'label' => '(=) Receita Líquida de Vendas'],
            ['code' => 'L07', 'accumulate' => 6,  'label' => '(=) Lucro Bruto'],
            ['code' => 'L09', 'accumulate' => 8,  'label' => '(=) Margem de Contribuição'],
            ['code' => 'L14', 'accumulate' => 13, 'label' => '(=) EBITDA'],
            ['code' => 'L18', 'accumulate' => 16, 'label' => '(=) Lucro Líquido'],
            ['code' => 'L20', 'accumulate' => 18, 'label' => '(=) Lucro Líquido s/ Cedro'],
        ];

        foreach ($cases as $case) {
            $line = DreManagementLine::where('code', $case['code'])->firstOrFail();
            $this->assertTrue($line->is_subtotal, "{$case['code']} deveria ser subtotal");
            $this->assertSame($case['accumulate'], $line->accumulate_until_sort_order,
                "{$case['code']} deveria acumular até {$case['accumulate']}");
            $this->assertSame($case['label'], $line->level_1);
        }
    }

    public function test_seeder_natures_are_normalized(): void
    {
        $revenue = DreManagementLine::where('nature', 'revenue')->pluck('code')->sort()->values();
        $this->assertSame(['L01', 'L17'], $revenue->all());

        // Subtotais: todos têm nature='subtotal'
        $subtotals = DreManagementLine::where('is_subtotal', true)->pluck('nature')->unique();
        $this->assertSame(['subtotal'], $subtotals->values()->all());
    }

    public function test_seeder_is_idempotent(): void
    {
        $before = DreManagementLine::count();

        // Roda de novo — não duplica.
        (new DreManagementLineSeeder())->run();

        $this->assertSame($before, DreManagementLine::count());
    }

    // -----------------------------------------------------------------
    // Coluna type em chart_of_accounts + backfill
    // -----------------------------------------------------------------

    public function test_chart_of_accounts_has_type_column(): void
    {
        $this->assertTrue(Schema::hasColumn('chart_of_accounts', 'type'));
    }

    public function test_backfill_maps_accepts_entries_to_type(): void
    {
        // O seed real do prompt #1 deixou contas com accepts_entries
        // populado. Backfill da migration 200001 preencheu `type`.
        $analyticalFromSeed = ChartOfAccount::where('accepts_entries', true)
            ->whereNotNull('type')
            ->first();

        if ($analyticalFromSeed) {
            $this->assertSame(AccountType::ANALYTICAL, $analyticalFromSeed->type);
        }

        $syntheticFromSeed = ChartOfAccount::where('accepts_entries', false)
            ->whereNotNull('type')
            ->first();

        if ($syntheticFromSeed) {
            $this->assertSame(AccountType::SYNTHETIC, $syntheticFromSeed->type);
        }
    }

    // -----------------------------------------------------------------
    // Enums
    // -----------------------------------------------------------------

    public function test_account_type_enum_short_code_roundtrip(): void
    {
        $this->assertSame('S', AccountType::SYNTHETIC->shortCode());
        $this->assertSame('A', AccountType::ANALYTICAL->shortCode());

        $this->assertSame(AccountType::SYNTHETIC, AccountType::fromShortCode('S'));
        $this->assertSame(AccountType::ANALYTICAL, AccountType::fromShortCode('a'));
        $this->assertNull(AccountType::fromShortCode('X'));
        $this->assertNull(AccountType::fromShortCode(null));
    }

    public function test_account_type_from_accepts_entries(): void
    {
        $this->assertSame(AccountType::ANALYTICAL, AccountType::fromAcceptsEntries(true));
        $this->assertSame(AccountType::SYNTHETIC, AccountType::fromAcceptsEntries(false));
        $this->assertTrue(AccountType::ANALYTICAL->acceptsEntries());
        $this->assertFalse(AccountType::SYNTHETIC->acceptsEntries());
    }

    public function test_account_group_from_code(): void
    {
        $this->assertSame(AccountGroup::ATIVO, AccountGroup::fromCode('1.1.1.01.00016'));
        $this->assertSame(AccountGroup::RECEITAS, AccountGroup::fromCode('3.1.1.01.00012'));
        $this->assertSame(AccountGroup::CUSTOS_DESPESAS, AccountGroup::fromCode('4.2.1.04.00032'));
        $this->assertNull(AccountGroup::fromCode('8.1.01')); // CC, não entra aqui
        $this->assertNull(AccountGroup::fromCode(''));
        $this->assertNull(AccountGroup::fromCode(null));
    }

    public function test_account_group_is_result_group(): void
    {
        $this->assertTrue(AccountGroup::RECEITAS->isResultGroup());
        $this->assertTrue(AccountGroup::CUSTOS_DESPESAS->isResultGroup());
        $this->assertTrue(AccountGroup::RESULTADO->isResultGroup());
        $this->assertFalse(AccountGroup::ATIVO->isResultGroup());
        $this->assertFalse(AccountGroup::PASSIVO->isResultGroup());
    }

    // -----------------------------------------------------------------
    // Scopes novos em ChartOfAccount
    // -----------------------------------------------------------------

    public function test_scope_by_group_accepts_enum_and_int(): void
    {
        $receitaInt = ChartOfAccount::byGroup(3)->count();
        $receitaEnum = ChartOfAccount::byGroup(AccountGroup::RECEITAS)->count();

        $this->assertSame($receitaInt, $receitaEnum);
        $this->assertGreaterThan(0, $receitaInt);
    }

    public function test_scope_children_of_uses_prefix_match(): void
    {
        ChartOfAccount::factory()->create(['code' => 'TEST.X.Y']);
        ChartOfAccount::factory()->create(['code' => 'TEST.X.Y.1']);
        ChartOfAccount::factory()->create(['code' => 'TEST.X.Y.2']);
        ChartOfAccount::factory()->create(['code' => 'TEST.X.Z']);

        $children = ChartOfAccount::childrenOf('TEST.X.Y')->pluck('code')->sort()->values();

        $this->assertSame(['TEST.X.Y.1', 'TEST.X.Y.2'], $children->all());
    }

    public function test_scope_analytical_and_synthetic_use_type_when_populated(): void
    {
        $a = ChartOfAccount::factory()->analytical()->create(['code' => 'TEST.ANA']);
        $s = ChartOfAccount::factory()->synthetic()->create(['code' => 'TEST.SYN']);

        $this->assertTrue(ChartOfAccount::analytical()->where('id', $a->id)->exists());
        $this->assertFalse(ChartOfAccount::analytical()->where('id', $s->id)->exists());

        $this->assertTrue(ChartOfAccount::synthetic()->where('id', $s->id)->exists());
        $this->assertFalse(ChartOfAccount::synthetic()->where('id', $a->id)->exists());
    }

    // -----------------------------------------------------------------
    // Scopes novos em DreActual / DreBudget
    // -----------------------------------------------------------------

    public function test_dre_actual_scope_for_period(): void
    {
        $account = ChartOfAccount::factory()->create();

        $inPeriod = DreActual::factory()->for($account, 'chartOfAccount')
            ->create(['entry_date' => '2026-03-15']);
        $outPeriod = DreActual::factory()->for($account, 'chartOfAccount')
            ->create(['entry_date' => '2026-05-15']);

        $results = DreActual::forPeriod('2026-03-01', '2026-03-31')->get();

        $this->assertTrue($results->contains('id', $inPeriod->id));
        $this->assertFalse($results->contains('id', $outPeriod->id));
    }

    public function test_dre_actual_scope_for_period_accepts_carbon(): void
    {
        $account = ChartOfAccount::factory()->create();
        DreActual::factory()->for($account, 'chartOfAccount')
            ->create(['entry_date' => '2026-02-10']);

        $count = DreActual::forPeriod(
            Carbon::parse('2026-02-01'),
            Carbon::parse('2026-02-28')
        )->count();

        $this->assertSame(1, $count);
    }

    public function test_dre_actual_scope_for_unit_accepts_int_array_and_model(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $account = ChartOfAccount::factory()->create();

        DreActual::factory()->for($account, 'chartOfAccount')->create(['store_id' => $store1->id]);
        DreActual::factory()->for($account, 'chartOfAccount')->create(['store_id' => $store2->id]);

        $this->assertSame(1, DreActual::forUnit($store1->id)->count());
        $this->assertSame(1, DreActual::forUnit($store1)->count());
        $this->assertSame(2, DreActual::forUnit([$store1->id, $store2->id])->count());
    }

    public function test_dre_actual_scope_for_cost_center(): void
    {
        $cc = CostCenter::factory()->create();
        $account = ChartOfAccount::factory()->create();

        $withCc = DreActual::factory()->for($account, 'chartOfAccount')
            ->create(['cost_center_id' => $cc->id]);
        $withoutCc = DreActual::factory()->for($account, 'chartOfAccount')
            ->create(['cost_center_id' => null]);

        $results = DreActual::forCostCenter($cc)->get();

        $this->assertTrue($results->contains('id', $withCc->id));
        $this->assertFalse($results->contains('id', $withoutCc->id));
    }

    public function test_dre_budget_scope_for_period_and_unit(): void
    {
        $account = ChartOfAccount::factory()->create();
        $store = Store::factory()->create();

        DreBudget::factory()->for($account, 'chartOfAccount')->create([
            'entry_date' => '2026-04-01',
            'store_id' => $store->id,
        ]);

        $count = DreBudget::forPeriod('2026-04-01', '2026-04-30')
            ->forUnit($store)
            ->count();

        $this->assertSame(1, $count);
    }

    // -----------------------------------------------------------------
    // Scope novo em DreMapping
    // -----------------------------------------------------------------

    public function test_dre_mapping_scope_effective_at_accepts_carbon(): void
    {
        $line = DreManagementLine::where('code', 'L01')->firstOrFail();
        $account = ChartOfAccount::factory()->create();

        DreMapping::factory()
            ->for($account, 'chartOfAccount')
            ->for($line, 'dreManagementLine')
            ->create([
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-06-30',
            ]);

        $inside = DreMapping::effectiveAt(Carbon::parse('2026-03-15'))->count();
        $outside = DreMapping::effectiveAt(Carbon::parse('2026-08-01'))->count();

        $this->assertSame(1, $inside);
        $this->assertSame(0, $outside);
    }

    // -----------------------------------------------------------------
    // Factory state atLevel
    // -----------------------------------------------------------------

    public function test_chart_of_account_factory_at_level_generates_matching_code(): void
    {
        $level0 = ChartOfAccount::factory()->atLevel(0)->create();
        $this->assertSame(0, $level0->classification_level);
        $this->assertSame(0, substr_count($level0->code, '.'));
        $this->assertFalse($level0->accepts_entries);
        $this->assertSame(AccountType::SYNTHETIC, $level0->type);

        $level4 = ChartOfAccount::factory()->atLevel(4)->create();
        $this->assertSame(4, $level4->classification_level);
        $this->assertSame(4, substr_count($level4->code, '.'));
        $this->assertTrue($level4->accepts_entries);
        $this->assertSame(AccountType::ANALYTICAL, $level4->type);
    }

    public function test_chart_of_account_factory_at_level_rejects_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ChartOfAccount::factory()->atLevel(5);
    }
}
