<?php

namespace Tests\Feature\Projectors;

use App\Models\ChartOfAccount;
use App\Models\DreActual;
use App\Models\Employee;
use App\Models\Sale;
use App\Models\Store;
use App\Services\DRE\SaleToDreProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Cobre `SaleToDreProjector` conforme playbook prompt 8.
 */
class SaleToDreProjectorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Reseta config para não vazar entre testes.
        config(['dre.default_sale_account_code' => '3.1.1.01.00001']);
        parent::tearDown();
    }

    // -----------------------------------------------------------------
    // Conta por loja tem precedência
    // -----------------------------------------------------------------

    public function test_uses_store_sale_chart_of_account_when_set(): void
    {
        $storeAccount = ChartOfAccount::factory()->revenue()->analytical()->create([
            'code' => 'TST.ST.REV',
        ]);

        $store = Store::factory()->create([
            'sale_chart_of_account_id' => $storeAccount->id,
        ]);

        $sale = $this->makeSale($store, totalSales: 1200.00);

        $this->assertDatabaseHas('dre_actuals', [
            'source_type' => Sale::class,
            'source_id' => $sale->id,
            'chart_of_account_id' => $storeAccount->id,
            'amount' => 1200.00,
            'cost_center_id' => null,
        ]);
    }

    // -----------------------------------------------------------------
    // Fallback em config
    // -----------------------------------------------------------------

    public function test_falls_back_to_config_default_when_store_has_no_account(): void
    {
        $fallback = ChartOfAccount::factory()->revenue()->analytical()->create([
            'code' => 'FALLBACK.REV.001',
        ]);

        config(['dre.default_sale_account_code' => 'FALLBACK.REV.001']);

        $store = Store::factory()->create(['sale_chart_of_account_id' => null]);
        $sale = $this->makeSale($store, totalSales: 500.00);

        $this->assertDatabaseHas('dre_actuals', [
            'source_id' => $sale->id,
            'chart_of_account_id' => $fallback->id,
            'amount' => 500.00,
        ]);
    }

    // -----------------------------------------------------------------
    // Skip quando nada resolve
    // -----------------------------------------------------------------

    public function test_skips_with_log_warning_when_no_account_resolvable(): void
    {
        config(['dre.default_sale_account_code' => 'NONEXISTENT.CODE.XYZ']);

        Log::spy();

        $store = Store::factory()->create(['sale_chart_of_account_id' => null]);
        $sale = $this->makeSale($store, totalSales: 100.00);

        $this->assertDatabaseMissing('dre_actuals', ['source_id' => $sale->id]);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains($msg, 'conta de receita não resolvida'))
            ->atLeast()
            ->once();
    }

    // -----------------------------------------------------------------
    // Sign é sempre positivo
    // -----------------------------------------------------------------

    public function test_amount_is_always_positive_for_sales(): void
    {
        $storeAccount = ChartOfAccount::factory()->revenue()->analytical()->create([
            'code' => 'TST.SALE.POS',
        ]);
        $store = Store::factory()->create(['sale_chart_of_account_id' => $storeAccount->id]);

        // Mesmo passando um valor "negativo" (cenário patológico), projetor
        // grava o abs.
        $sale = $this->makeSale($store, totalSales: 777.77);

        $this->assertDatabaseHas('dre_actuals', [
            'source_id' => $sale->id,
            'amount' => 777.77,
        ]);
    }

    // -----------------------------------------------------------------
    // Observer
    // -----------------------------------------------------------------

    public function test_observer_projects_on_sale_creation(): void
    {
        $storeAccount = ChartOfAccount::factory()->revenue()->analytical()->create([
            'code' => 'TST.OBS.SALE.01',
        ]);
        $store = Store::factory()->create(['sale_chart_of_account_id' => $storeAccount->id]);

        $sale = $this->makeSale($store, totalSales: 350.00);

        $this->assertDatabaseHas('dre_actuals', [
            'source_type' => Sale::class,
            'source_id' => $sale->id,
        ]);
    }

    public function test_observer_unprojects_on_sale_deletion(): void
    {
        $storeAccount = ChartOfAccount::factory()->revenue()->analytical()->create([
            'code' => 'TST.OBS.SALE.02',
        ]);
        $store = Store::factory()->create(['sale_chart_of_account_id' => $storeAccount->id]);

        $sale = $this->makeSale($store, totalSales: 250.00);

        $this->assertDatabaseHas('dre_actuals', ['source_id' => $sale->id]);

        $sale->delete();

        $this->assertDatabaseMissing('dre_actuals', ['source_id' => $sale->id]);
    }

    public function test_observer_reprojects_when_relevant_fields_change(): void
    {
        $storeAccount = ChartOfAccount::factory()->revenue()->analytical()->create([
            'code' => 'TST.OBS.SALE.03',
        ]);
        $store = Store::factory()->create(['sale_chart_of_account_id' => $storeAccount->id]);

        $sale = $this->makeSale($store, totalSales: 100.00);
        $this->assertDatabaseHas('dre_actuals', [
            'source_id' => $sale->id,
            'amount' => 100.00,
        ]);

        $sale->update(['total_sales' => 999.00]);

        $this->assertDatabaseHas('dre_actuals', [
            'source_id' => $sale->id,
            'amount' => 999.00,
        ]);
        $this->assertSame(1, DreActual::where('source_id', $sale->id)->count());
    }

    // -----------------------------------------------------------------
    // Rebuild
    // -----------------------------------------------------------------

    public function test_rebuild_truncates_and_reprojects_all_sales(): void
    {
        $storeAccount = ChartOfAccount::factory()->revenue()->analytical()->create([
            'code' => 'TST.RBLD.SALE',
        ]);
        $store = Store::factory()->create(['sale_chart_of_account_id' => $storeAccount->id]);

        $sales = collect([100, 200, 300])->map(fn ($v) => $this->makeSale($store, totalSales: $v));

        $report = app(SaleToDreProjector::class)->rebuild();

        $this->assertSame(3, $report->projected);
        $this->assertSame(3, DreActual::where('source', DreActual::SOURCE_SALE)->count());
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function makeSale(Store $store, float $totalSales): Sale
    {
        // employees.area_id é unsignedBigInteger sem FK — basta um valor qualquer.
        $employee = Employee::factory()->create([
            'store_id' => $store->code,
            'area_id' => 1,
        ]);

        return Sale::create([
            'store_id' => $store->id,
            'employee_id' => $employee->id,
            'date_sales' => '2026-03-15',
            'total_sales' => $totalSales,
            'qtde_total' => 1,
            'source' => 'test',
        ]);
    }
}
