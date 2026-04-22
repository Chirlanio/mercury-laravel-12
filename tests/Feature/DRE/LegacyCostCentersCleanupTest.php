<?php

namespace Tests\Feature\DRE;

use App\Enums\AccountType;
use App\Events\DRE\AnalyticalAccountCreated;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Cobre os 3 destravadores D2 pré-prompt 6:
 *   - Command `dre:check-legacy-cc-refs`.
 *   - Migration `soft_delete_legacy_cost_centers` (condicional).
 *   - ChartOfAccountObserver + AnalyticalAccountCreated event.
 */
class LegacyCostCentersCleanupTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Command de verificação
    // -----------------------------------------------------------------

    public function test_check_command_reports_no_legacy_when_all_ccs_have_source(): void
    {
        // Limpa os legados que eventualmente vieram do seed base.
        CostCenter::whereNull('external_source')->whereNull('deleted_at')->update([
            'external_source' => 'TEST',
        ]);

        $this->artisan('dre:check-legacy-cc-refs')
            ->expectsOutputToContain('Nenhum CC legado encontrado')
            ->assertSuccessful();
    }

    public function test_check_command_lists_legacy_ccs_without_source(): void
    {
        CostCenter::factory()->create(['code' => 'LEG-TST-001', 'external_source' => null]);

        $this->artisan('dre:check-legacy-cc-refs')
            ->expectsOutputToContain('CCs legados')
            ->assertExitCode(0); // sem refs ativas, exit=0
    }

    // -----------------------------------------------------------------
    // Migration de limpeza (via re-run manual)
    // -----------------------------------------------------------------

    public function test_migration_soft_deletes_legacy_without_references(): void
    {
        $legacy = CostCenter::factory()->create([
            'code' => 'LEG-TST-002',
            'external_source' => null,
            'is_active' => true,
        ]);

        // Re-rodar a migration agora.
        $migration = require database_path('migrations/tenant/2026_04_22_400002_soft_delete_legacy_cost_centers.php');
        $migration->up();

        $fresh = $legacy->fresh();
        $this->assertNotNull($fresh->deleted_at);
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertStringContainsString('CC legado', $fresh->deleted_reason);
    }

    public function test_migration_marks_blocked_legacy_as_unmigrated_instead_of_deleting(): void
    {
        $blocked = CostCenter::factory()->create([
            'code' => 'LEG-TST-BLK',
            'external_source' => null,
        ]);
        $free = CostCenter::factory()->create([
            'code' => 'LEG-TST-FREE',
            'external_source' => null,
        ]);

        // Simula management_class ativa apontando só para o $blocked.
        \DB::table('management_classes')->insert([
            'code' => 'TST.LEG.01',
            'name' => 'Legacy Ref',
            'accepts_entries' => true,
            'is_active' => true,
            'cost_center_id' => $blocked->id,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/tenant/2026_04_22_400002_soft_delete_legacy_cost_centers.php');
        $migration->up();

        $freshBlocked = $blocked->fresh();
        $this->assertNull($freshBlocked->deleted_at, 'CC com ref ativa não pode ser deletado.');
        $this->assertSame('LEGACY_UNMIGRATED', $freshBlocked->external_source);

        $freshFree = $free->fresh();
        $this->assertNotNull($freshFree->deleted_at, 'CC sem ref deve ser soft-deletado.');
        $this->assertStringContainsString('CC legado', $freshFree->deleted_reason);
    }

    public function test_migration_is_noop_when_no_legacy_exists(): void
    {
        CostCenter::whereNull('external_source')->whereNull('deleted_at')->update([
            'external_source' => 'TEST',
        ]);

        $before = CostCenter::whereNotNull('deleted_at')->count();

        $migration = require database_path('migrations/tenant/2026_04_22_400002_soft_delete_legacy_cost_centers.php');
        $migration->up();

        $after = CostCenter::whereNotNull('deleted_at')->count();

        $this->assertSame($before, $after);
    }

    // -----------------------------------------------------------------
    // Observer + Event
    // -----------------------------------------------------------------

    public function test_observer_dispatches_event_for_analytical_account_in_result_group(): void
    {
        Event::fake([AnalyticalAccountCreated::class]);

        $account = ChartOfAccount::factory()->create([
            'code' => 'TEST.OBS.RES',
            'type' => AccountType::ANALYTICAL->value,
            'accepts_entries' => true,
            'account_group' => 3, // Receitas
        ]);

        Event::assertDispatched(
            AnalyticalAccountCreated::class,
            fn (AnalyticalAccountCreated $e) => $e->account->id === $account->id
        );
    }

    public function test_observer_does_not_dispatch_for_synthetic_account(): void
    {
        Event::fake([AnalyticalAccountCreated::class]);

        ChartOfAccount::factory()->create([
            'code' => 'TEST.OBS.SYN',
            'type' => AccountType::SYNTHETIC->value,
            'accepts_entries' => false,
            'account_group' => 3,
        ]);

        Event::assertNotDispatched(AnalyticalAccountCreated::class);
    }

    public function test_observer_does_not_dispatch_for_asset_group(): void
    {
        Event::fake([AnalyticalAccountCreated::class]);

        ChartOfAccount::factory()->create([
            'code' => 'TEST.OBS.ASSET',
            'type' => AccountType::ANALYTICAL->value,
            'accepts_entries' => true,
            'account_group' => 1, // Ativo — fora do DRE
        ]);

        Event::assertNotDispatched(AnalyticalAccountCreated::class);
    }

    public function test_observer_does_not_dispatch_for_liability_group(): void
    {
        Event::fake([AnalyticalAccountCreated::class]);

        ChartOfAccount::factory()->create([
            'code' => 'TEST.OBS.LIAB',
            'type' => AccountType::ANALYTICAL->value,
            'accepts_entries' => true,
            'account_group' => 2, // Passivo
        ]);

        Event::assertNotDispatched(AnalyticalAccountCreated::class);
    }
}
