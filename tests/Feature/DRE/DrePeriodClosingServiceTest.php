<?php

namespace Tests\Feature\DRE;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreActual;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use App\Models\DrePeriodClosing;
use App\Models\DrePeriodClosingSnapshot;
use App\Models\OrderPayment;
use App\Models\Store;
use App\Models\User;
use App\Services\DRE\DreMappingResolver;
use App\Services\DRE\DreMappingService;
use App\Services\DRE\DrePeriodClosingService;
use App\Services\DRE\OrderPaymentToDreProjector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre `DrePeriodClosingService` — close/reopen + enforcement no
 * `DreMappingService` + import manual + projetor com flag de período
 * fechado.
 *
 * Usa o tenant de teste (seed) porque precisa de lines reais da DRE +
 * resolver funcional.
 */
class DrePeriodClosingServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        DreMappingResolver::resetCache();
    }

    // -----------------------------------------------------------------
    // Close
    // -----------------------------------------------------------------

    public function test_close_creates_period_closing_and_snapshots(): void
    {
        [$store, $account, $line] = $this->makeMappedScenario();

        // Insere 1 actual no escopo Geral+Loja.
        $this->makeActual($store, $account, 1000.00, '2026-03-15');

        $closing = app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        $this->assertInstanceOf(DrePeriodClosing::class, $closing);
        $this->assertSame('2026-03-31', $closing->closed_up_to_date->format('Y-m-d'));

        // Deve ter pelo menos 1 snapshot general + 1 store para a linha mapeada.
        $this->assertTrue(
            DrePeriodClosingSnapshot::query()
                ->where('dre_period_closing_id', $closing->id)
                ->where('scope', DrePeriodClosingSnapshot::SCOPE_GENERAL)
                ->where('year_month', '2026-03')
                ->where('dre_management_line_id', $line->id)
                ->exists(),
            'Expected GENERAL snapshot for 2026-03 / mapped line.',
        );

        $this->assertTrue(
            DrePeriodClosingSnapshot::query()
                ->where('dre_period_closing_id', $closing->id)
                ->where('scope', DrePeriodClosingSnapshot::SCOPE_STORE)
                ->where('scope_id', $store->id)
                ->where('year_month', '2026-03')
                ->exists(),
            'Expected STORE snapshot for the affected store.',
        );
    }

    public function test_close_rejects_date_equal_or_before_last_close(): void
    {
        app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        $this->expectException(ValidationException::class);
        app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );
    }

    // -----------------------------------------------------------------
    // Matriz com snapshot — overlay real
    // -----------------------------------------------------------------

    public function test_matrix_reads_from_snapshot_after_retroactive_actual(): void
    {
        [$store, $account, $line] = $this->makeMappedScenario();

        $this->makeActual($store, $account, 1000.00, '2026-03-15');

        app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        // Lançamento retroativo após o fechamento — matriz NÃO deve refletir.
        $this->makeActual($store, $account, 500.00, '2026-03-20');

        $matrix = app(\App\Services\DRE\DreMatrixService::class)->matrix([
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
        ]);

        $lineRow = collect($matrix['lines'])->firstWhere('id', $line->id);
        $this->assertNotNull($lineRow);
        $marchCell = $lineRow['months']['2026-03'] ?? null;
        $this->assertNotNull($marchCell);
        // Valor do snapshot (1000) prevalece sobre 1000+500 live.
        $this->assertEquals(1000.00, round($marchCell['actual'], 2));
    }

    // -----------------------------------------------------------------
    // Enforcement no DreMappingService
    // -----------------------------------------------------------------

    public function test_mapping_create_in_closed_period_is_blocked(): void
    {
        [, $account] = $this->makeMappedScenario();

        app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        $line = DreManagementLine::where('code', 'L99_UNCLASSIFIED')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(DreMappingService::class)->create([
            'chart_of_account_id' => $account->id,
            'cost_center_id' => null,
            'dre_management_line_id' => $line->id,
            'effective_from' => '2026-03-01',
            'effective_to' => null,
        ]);
    }

    public function test_mapping_create_after_closed_period_is_allowed(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'PER.AC.'.fake()->unique()->numerify('###'),
            'account_group' => 4,
        ]);

        app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        $line = DreManagementLine::where('code', 'L99_UNCLASSIFIED')->firstOrFail();

        $mapping = app(DreMappingService::class)->create([
            'chart_of_account_id' => $account->id,
            'cost_center_id' => null,
            'dre_management_line_id' => $line->id,
            'effective_from' => '2026-04-01',
            'effective_to' => null,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $this->assertNotNull($mapping->id);
    }

    // -----------------------------------------------------------------
    // Enforcement no DreActualsImporter
    // -----------------------------------------------------------------

    public function test_actuals_importer_blocks_entry_in_closed_period(): void
    {
        [$store, $account] = $this->makeMappedScenario();

        app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dre-actuals-closed-'.uniqid().'.xlsx';
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray([
            'entry_date', 'store_code', 'account_code', 'cost_center_code',
            'amount', 'document', 'description', 'external_id',
        ], null, 'A1');
        $sheet->fromArray([
            ['2026-03-15', $store->code, $account->code, '', 100.00, '', '', ''],
        ], null, 'A2');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($path);

        $report = app(\App\Services\DRE\DreActualsImporter::class)->import($path);
        @unlink($path);

        $this->assertSame(1, $report->skipped);
        $this->assertSame(0, $report->created);
        $this->assertStringContainsString('período fechado', $report->errors[0]);
    }

    // -----------------------------------------------------------------
    // Reopen
    // -----------------------------------------------------------------

    public function test_reopen_marks_closing_and_deletes_snapshots(): void
    {
        [$store, $account] = $this->makeMappedScenario();
        $this->makeActual($store, $account, 1000.00, '2026-03-15');

        $closing = app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        $this->assertGreaterThan(
            0,
            DrePeriodClosingSnapshot::where('dre_period_closing_id', $closing->id)->count(),
        );

        $report = app(DrePeriodClosingService::class)->reopen(
            closing: $closing,
            reopenedBy: $this->adminUser,
            reason: 'Erro de lançamento identificado via conciliação',
        );

        $closing->refresh();
        $this->assertNotNull($closing->reopened_at);
        $this->assertSame('Erro de lançamento identificado via conciliação', $closing->reopen_reason);
        $this->assertSame(
            0,
            DrePeriodClosingSnapshot::where('dre_period_closing_id', $closing->id)->count(),
        );
        $this->assertGreaterThan(0, $report->snapshotsDeleted);
    }

    public function test_reopen_requires_non_empty_reason(): void
    {
        $closing = app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        $this->expectException(ValidationException::class);
        app(DrePeriodClosingService::class)->reopen(
            closing: $closing,
            reopenedBy: $this->adminUser,
            reason: '   ',
        );
    }

    public function test_reopen_computes_diffs_against_current_live(): void
    {
        [$store, $account] = $this->makeMappedScenario();
        $this->makeActual($store, $account, 1000.00, '2026-03-15');

        $closing = app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        // Injeta um lançamento retroativo — deve aparecer como diff.
        $this->makeActual($store, $account, 500.00, '2026-03-20');

        $report = app(DrePeriodClosingService::class)->previewReopenDiffs($closing);

        $this->assertTrue($report->hasDiffs());
        $hasDeltaPositive = collect($report->diffs)->contains(fn ($d) => (float) $d['delta'] > 0);
        $this->assertTrue($hasDeltaPositive, 'Expected at least one positive delta (live > snapshot).');
    }

    public function test_reclose_after_reopen_creates_new_snapshots(): void
    {
        [$store, $account] = $this->makeMappedScenario();
        $this->makeActual($store, $account, 1000.00, '2026-03-15');

        $firstClose = app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        app(DrePeriodClosingService::class)->reopen(
            closing: $firstClose,
            reopenedBy: $this->adminUser,
            reason: 'Recomputar após ajuste',
        );

        // Ajuste retroativo + refechar.
        $this->makeActual($store, $account, 500.00, '2026-03-25');
        $secondClose = app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        $this->assertNotSame($firstClose->id, $secondClose->id);
        $snapshotCount = DrePeriodClosingSnapshot::where('dre_period_closing_id', $secondClose->id)->count();
        $this->assertGreaterThan(0, $snapshotCount);
    }

    // -----------------------------------------------------------------
    // Projetor — flag `reported_in_closed_period`
    // -----------------------------------------------------------------

    public function test_order_payment_projected_in_closed_period_sets_flag(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'PER.EXP.'.fake()->unique()->numerify('###'),
            'account_group' => 4,
        ]);

        app(DrePeriodClosingService::class)->close(
            closedUpToDate: Carbon::parse('2026-03-31'),
            closedBy: $this->adminUser,
        );

        // OP com competence_date em março — dentro do fechamento.
        $op = OrderPayment::create([
            'description' => 'Retroativo',
            'total_value' => 250,
            'date_payment' => '2026-04-05',
            'competence_date' => '2026-03-20',
            'accounting_class_id' => $account->id,
            'status' => OrderPayment::STATUS_DONE,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->assertDatabaseHas('dre_actuals', [
            'source_id' => $op->id,
            'reported_in_closed_period' => true,
        ]);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Cria 1 store + 1 analytical account + L99 mapping + devolve
     * [store, account, line] para os testes usarem.
     *
     * @return array{0: Store, 1: ChartOfAccount, 2: DreManagementLine}
     */
    private function makeMappedScenario(): array
    {
        $store = Store::factory()->create();
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'PER.AC.'.fake()->unique()->numerify('###'),
            'account_group' => 4,
        ]);

        $line = DreManagementLine::where('code', 'L99_UNCLASSIFIED')->firstOrFail();

        return [$store, $account, $line];
    }

    private function makeActual(Store $store, ChartOfAccount $account, float $amount, string $date): DreActual
    {
        return DreActual::create([
            'entry_date' => $date,
            'chart_of_account_id' => $account->id,
            'cost_center_id' => null,
            'store_id' => $store->id,
            'amount' => $amount,
            'source' => DreActual::SOURCE_MANUAL_IMPORT,
            'source_type' => null,
            'source_id' => null,
            'reported_in_closed_period' => false,
        ]);
    }
}
