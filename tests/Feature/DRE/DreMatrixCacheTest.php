<?php

namespace Tests\Feature\DRE;

use App\Models\ChartOfAccount;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use App\Models\Store;
use App\Services\DRE\DreMatrixService;
use App\Support\DreCacheVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre a camada de cache da matriz DRE (playbook prompt 12):
 *   - Cache hit: segunda chamada vem do cache.
 *   - Invalidação: salvar DreMapping incrementa version + muda a chave.
 *   - Normalizador: filtros equivalentes produzem mesma chave.
 *   - Warm-up command: não quebra sem dados.
 */
class DreMatrixCacheTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        Cache::flush();
    }

    // -----------------------------------------------------------------
    // Cache hit
    // -----------------------------------------------------------------

    public function test_second_call_hits_cache(): void
    {
        $filter = [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'scope' => 'general',
        ];

        $service = app(DreMatrixService::class);
        $key = DreMatrixService::cacheKeyForFilter($filter);

        $this->assertNull(Cache::get($key), 'Cache key should be empty before first call.');

        $service->matrix($filter);
        $cached = Cache::get($key);

        $this->assertIsArray($cached, 'After first call the key should be populated.');
        $this->assertArrayHasKey('lines', $cached);
    }

    // -----------------------------------------------------------------
    // Invalidação via model save
    // -----------------------------------------------------------------

    public function test_saving_dre_mapping_bumps_cache_version(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'CACHE.INV.'.fake()->unique()->numerify('###'),
            'account_group' => 4,
        ]);
        $line = DreManagementLine::where('code', 'L99_UNCLASSIFIED')->firstOrFail();

        $before = DreCacheVersion::current();

        DreMapping::create([
            'chart_of_account_id' => $account->id,
            'cost_center_id' => null,
            'dre_management_line_id' => $line->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $after = DreCacheVersion::current();
        $this->assertGreaterThan($before, $after, 'Cache version should increment on save.');
    }

    public function test_cache_version_changes_key(): void
    {
        $filter = [
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'scope' => 'general',
        ];

        $keyBefore = DreMatrixService::cacheKeyForFilter($filter);
        DreCacheVersion::invalidate();
        $keyAfter = DreMatrixService::cacheKeyForFilter($filter);

        $this->assertNotSame($keyBefore, $keyAfter);
    }

    // -----------------------------------------------------------------
    // Normalizador
    // -----------------------------------------------------------------

    public function test_normalize_yields_same_key_for_equivalent_filters(): void
    {
        $store = Store::factory()->create();
        $anotherStore = Store::factory()->create();

        $a = [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'store_ids' => [$store->id, $anotherStore->id],
            'scope' => 'store',
            'include_unclassified' => true,
        ];

        // Mesmos valores com ordem distinta + key ordering diferente.
        $b = [
            'scope' => 'STORE',
            'store_ids' => [$anotherStore->id, $store->id, $store->id],
            'end_date' => '2026-12-31',
            'start_date' => '2026-01-01',
            'include_unclassified' => true,
        ];

        $this->assertSame(
            DreMatrixService::cacheKeyForFilter($a),
            DreMatrixService::cacheKeyForFilter($b),
        );
    }

    public function test_normalize_differs_for_different_budget_versions(): void
    {
        $base = ['start_date' => '2026-01-01', 'end_date' => '2026-03-31'];

        $this->assertNotSame(
            DreMatrixService::cacheKeyForFilter($base + ['budget_version' => 'v1']),
            DreMatrixService::cacheKeyForFilter($base + ['budget_version' => 'v2']),
        );
    }

    // -----------------------------------------------------------------
    // Warm-up command
    // -----------------------------------------------------------------

    public function test_warm_cache_command_runs_on_empty_tenant(): void
    {
        $this->artisan('dre:warm-cache')->assertExitCode(0);
    }

    // -----------------------------------------------------------------
    // DreCacheVersion helper (unit-ish)
    // -----------------------------------------------------------------

    public function test_cache_version_starts_at_one_and_increments(): void
    {
        Cache::flush();

        $this->assertSame(1, DreCacheVersion::current());
        $this->assertSame(2, DreCacheVersion::invalidate());
        $this->assertSame(3, DreCacheVersion::invalidate());
        $this->assertSame(3, DreCacheVersion::current());
    }
}
