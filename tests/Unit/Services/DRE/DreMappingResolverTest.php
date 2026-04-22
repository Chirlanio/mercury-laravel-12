<?php

namespace Tests\Unit\Services\DRE;

use App\Models\DreManagementLine;
use App\Services\DRE\DreMappingResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre `DreMappingResolver` com os 8 cenários do `dre-playbook.md` §prompt 6.
 *
 * Os cenários que NÃO hitam fallback usam o construtor direto com arrays
 * sintéticos — puro PHP, sem DB. O cenário "nenhum match → L99" e o lookup
 * do id da fantasma exigem DB (a L99 é seedada por migration), portanto
 * usamos RefreshDatabase.
 */
class DreMappingResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DreMappingResolver::resetCache();
    }

    public function test_resolves_specific_when_only_specific_exists(): void
    {
        $resolver = new DreMappingResolver([
            $this->mapping(account: 10, cc: 99, line: 777, from: '2026-01-01', to: null),
        ]);

        $this->assertSame(
            777,
            $resolver->resolve(10, 99, Carbon::parse('2026-05-15'))
        );
    }

    public function test_resolves_wildcard_when_only_wildcard_exists(): void
    {
        $resolver = new DreMappingResolver([
            $this->mapping(account: 10, cc: null, line: 555, from: '2026-01-01', to: null),
        ]);

        $this->assertSame(
            555,
            $resolver->resolve(10, 99, Carbon::parse('2026-05-15'))
        );
    }

    public function test_specific_wins_over_wildcard_when_both_exist(): void
    {
        $resolver = new DreMappingResolver([
            $this->mapping(account: 10, cc: null, line: 555, from: '2026-01-01', to: null),
            $this->mapping(account: 10, cc: 99, line: 777, from: '2026-01-01', to: null),
        ]);

        $this->assertSame(
            777,
            $resolver->resolve(10, 99, Carbon::parse('2026-05-15'))
        );
    }

    public function test_falls_to_wildcard_when_specific_is_expired_on_date(): void
    {
        $resolver = new DreMappingResolver([
            $this->mapping(account: 10, cc: 99, line: 777, from: '2026-01-01', to: '2026-03-31'),
            $this->mapping(account: 10, cc: null, line: 555, from: '2026-01-01', to: null),
        ]);

        // 2026-05-15 está fora da vigência do específico (que expirou 03-31).
        $this->assertSame(
            555,
            $resolver->resolve(10, 99, Carbon::parse('2026-05-15'))
        );
    }

    public function test_two_specific_mappings_in_adjacent_periods_resolve_by_date(): void
    {
        $resolver = new DreMappingResolver([
            $this->mapping(account: 10, cc: 99, line: 100, from: '2026-01-01', to: '2026-06-30'),
            $this->mapping(account: 10, cc: 99, line: 200, from: '2026-07-01', to: null),
        ]);

        $this->assertSame(100, $resolver->resolve(10, 99, Carbon::parse('2026-03-15')));
        $this->assertSame(200, $resolver->resolve(10, 99, Carbon::parse('2026-09-15')));
    }

    public function test_unknown_account_id_returns_unclassified(): void
    {
        // Só exige DB para resolver UNCLASSIFIED.
        $unclassifiedId = DreMappingResolver::unclassifiedLineId();

        $resolver = new DreMappingResolver([]);

        $this->assertSame(
            $unclassifiedId,
            $resolver->resolve(42, 99, Carbon::parse('2026-05-15'))
        );
    }

    public function test_nothing_active_on_date_returns_unclassified(): void
    {
        $unclassifiedId = DreMappingResolver::unclassifiedLineId();

        $resolver = new DreMappingResolver([
            $this->mapping(account: 10, cc: 99, line: 777, from: '2026-01-01', to: '2026-03-31'),
        ]);

        $this->assertSame(
            $unclassifiedId,
            $resolver->resolve(10, 99, Carbon::parse('2026-05-15'))
        );
    }

    public function test_null_cost_center_goes_straight_to_wildcard(): void
    {
        $resolver = new DreMappingResolver([
            $this->mapping(account: 10, cc: null, line: 555, from: '2026-01-01', to: null),
            // Um específico qualquer — não deve ser escolhido porque o caller
            // passou cost_center_id=null (tipico de Sales que não têm CC).
            $this->mapping(account: 10, cc: 99, line: 777, from: '2026-01-01', to: null),
        ]);

        $this->assertSame(
            555,
            $resolver->resolve(10, null, Carbon::parse('2026-05-15'))
        );
    }

    public function test_resolve_many_batches_tuples_preserving_order(): void
    {
        $unclassifiedId = DreMappingResolver::unclassifiedLineId();

        $resolver = new DreMappingResolver([
            $this->mapping(account: 10, cc: 99, line: 777, from: '2026-01-01', to: null),
            $this->mapping(account: 20, cc: null, line: 555, from: '2026-01-01', to: null),
        ]);

        $results = $resolver->resolveMany([
            ['account_id' => 10, 'cost_center_id' => 99, 'date' => '2026-05-15'],
            ['account_id' => 20, 'cost_center_id' => 99, 'date' => '2026-05-15'],
            ['account_id' => 99, 'cost_center_id' => null, 'date' => '2026-05-15'],
        ]);

        $this->assertSame([777, 555, $unclassifiedId], array_values($results));
    }

    public function test_load_for_period_reads_mappings_from_db(): void
    {
        $line = DreManagementLine::where('code', 'L01')->firstOrFail();
        $account = \App\Models\ChartOfAccount::factory()->revenue()->create(['code' => 'LOADER.TST.01']);

        \App\Models\DreMapping::factory()
            ->for($account, 'chartOfAccount')
            ->for($line, 'dreManagementLine')
            ->create([
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'created_by_user_id' => \App\Models\User::factory()->create()->id,
            ]);

        $resolver = DreMappingResolver::loadForPeriod(
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-12-31')
        );

        $this->assertSame(
            $line->id,
            $resolver->resolve($account->id, null, Carbon::parse('2026-05-15'))
        );
    }

    // -----------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------

    private function mapping(int $account, ?int $cc, int $line, string $from, ?string $to): array
    {
        return [
            'chart_of_account_id' => $account,
            'cost_center_id' => $cc,
            'dre_management_line_id' => $line,
            'effective_from' => $from,
            'effective_to' => $to,
        ];
    }
}
