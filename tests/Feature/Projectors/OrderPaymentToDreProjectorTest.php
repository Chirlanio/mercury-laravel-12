<?php

namespace Tests\Feature\Projectors;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreActual;
use App\Models\OrderPayment;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DRE\OrderPaymentToDreProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre `OrderPaymentToDreProjector` conforme playbook prompt 8.
 *
 * O observer `OrderPaymentDreObserver` está registrado em
 * `AppServiceProvider` — então testes que criam/atualizam OrderPayment
 * exercitam o caminho completo (observer → projector → dre_actuals).
 */
class OrderPaymentToDreProjectorTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // project() direto
    // -----------------------------------------------------------------

    public function test_order_payment_not_done_does_not_project(): void
    {
        $op = $this->makeOrderPayment(status: OrderPayment::STATUS_BACKLOG);

        app(OrderPaymentToDreProjector::class)->project($op);

        $this->assertDatabaseMissing('dre_actuals', [
            'source_type' => OrderPayment::class,
            'source_id' => $op->id,
        ]);
    }

    public function test_order_payment_in_expense_group_projects_as_negative(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.OP.G4',
            'account_group' => 4,
        ]);

        $op = $this->makeOrderPayment(
            status: OrderPayment::STATUS_DONE,
            accountingClassId: $account->id,
            totalValue: 1500.00,
        );

        app(OrderPaymentToDreProjector::class)->project($op);

        $this->assertDatabaseHas('dre_actuals', [
            'source_type' => OrderPayment::class,
            'source_id' => $op->id,
            'chart_of_account_id' => $account->id,
            'amount' => -1500.00,
        ]);
    }

    public function test_order_payment_in_revenue_group_projects_as_positive(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.OP.G3',
            'account_group' => 3,
        ]);

        $op = $this->makeOrderPayment(
            status: OrderPayment::STATUS_DONE,
            accountingClassId: $account->id,
            totalValue: 2000.00,
        );

        app(OrderPaymentToDreProjector::class)->project($op);

        $this->assertDatabaseHas('dre_actuals', [
            'source_id' => $op->id,
            'amount' => 2000.00,
        ]);
    }

    public function test_order_payment_with_asset_group_raises_domain_exception(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.OP.G1',
            'account_group' => 1, // Ativo — não pertence à DRE
        ]);

        $op = $this->makeOrderPayment(
            status: OrderPayment::STATUS_DONE,
            accountingClassId: $account->id,
            totalValue: 500.00,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('não pode projetar para DRE');

        app(OrderPaymentToDreProjector::class)->project($op);
    }

    public function test_upsert_is_idempotent(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.OP.IDEMP',
            'account_group' => 4,
        ]);
        $op = $this->makeOrderPayment(
            status: OrderPayment::STATUS_DONE,
            accountingClassId: $account->id,
            totalValue: 100.00,
        );

        $projector = app(OrderPaymentToDreProjector::class);
        $projector->project($op);
        $projector->project($op);
        $projector->project($op);

        $this->assertSame(
            1,
            DreActual::where('source_type', OrderPayment::class)
                ->where('source_id', $op->id)
                ->count()
        );
    }

    // -----------------------------------------------------------------
    // Observer — fluxo de status
    // -----------------------------------------------------------------

    public function test_observer_projects_on_transition_to_done(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.OBS.001',
            'account_group' => 4,
        ]);

        $op = $this->makeOrderPayment(
            status: OrderPayment::STATUS_BACKLOG,
            accountingClassId: $account->id,
            totalValue: 300.00,
        );

        $this->assertDatabaseMissing('dre_actuals', ['source_id' => $op->id]);

        $op->status = OrderPayment::STATUS_DOING;
        $op->save();
        $op->status = OrderPayment::STATUS_WAITING;
        $op->save();
        $op->status = OrderPayment::STATUS_DONE;
        $op->save();

        $this->assertDatabaseHas('dre_actuals', [
            'source_id' => $op->id,
            'amount' => -300.00,
        ]);
    }

    public function test_observer_unprojects_when_leaving_done(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.OBS.002',
            'account_group' => 4,
        ]);

        $op = $this->makeOrderPayment(
            status: OrderPayment::STATUS_DONE,
            accountingClassId: $account->id,
            totalValue: 200.00,
        );

        $this->assertDatabaseHas('dre_actuals', ['source_id' => $op->id]);

        $op->status = OrderPayment::STATUS_WAITING;
        $op->save();

        $this->assertDatabaseMissing('dre_actuals', ['source_id' => $op->id]);
    }

    public function test_observer_unprojects_when_order_payment_is_deleted(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.OBS.003',
            'account_group' => 4,
        ]);

        $op = $this->makeOrderPayment(
            status: OrderPayment::STATUS_DONE,
            accountingClassId: $account->id,
            totalValue: 400.00,
        );

        $this->assertDatabaseHas('dre_actuals', ['source_id' => $op->id]);

        $op->delete();

        $this->assertDatabaseMissing('dre_actuals', ['source_id' => $op->id]);
    }

    // -----------------------------------------------------------------
    // Rebuild
    // -----------------------------------------------------------------

    public function test_rebuild_truncates_and_reprojects_all_done_order_payments(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'TST.RBLD.001',
            'account_group' => 4,
        ]);

        // 3 OPs done projetados.
        $dones = collect([1, 2, 3])->map(fn ($i) => $this->makeOrderPayment(
            status: OrderPayment::STATUS_DONE,
            accountingClassId: $account->id,
            totalValue: 100 * $i,
        ));

        // 1 OP não-done para garantir que não entra.
        $this->makeOrderPayment(
            status: OrderPayment::STATUS_BACKLOG,
            accountingClassId: $account->id,
            totalValue: 999,
        );

        // Sujeira pré-existente: insere uma linha órfã com source=ORDER_PAYMENT
        // e source_id que não existe.
        DreActual::create([
            'entry_date' => '2026-01-01',
            'chart_of_account_id' => $account->id,
            'amount' => -9999,
            'source' => DreActual::SOURCE_ORDER_PAYMENT,
            'source_type' => OrderPayment::class,
            'source_id' => 99999,
        ]);

        $report = app(OrderPaymentToDreProjector::class)->rebuild();

        $this->assertSame(4, $report->truncated); // 3 projetados automaticamente pelo observer + 1 órfão
        $this->assertSame(3, $report->projected);
        $this->assertSame(3, DreActual::where('source', DreActual::SOURCE_ORDER_PAYMENT)->count());

        foreach ($dones as $op) {
            $this->assertDatabaseHas('dre_actuals', ['source_id' => $op->id]);
        }
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function makeOrderPayment(
        string $status,
        ?int $accountingClassId = null,
        float $totalValue = 100.00,
    ): OrderPayment {
        $store = Store::factory()->create();
        $cc = CostCenter::factory()->create();
        $supplier = Supplier::create([
            'codigo_for' => 'TST-'.uniqid(),
            'cnpj' => null,
            'razao_social' => 'Fornecedor Teste',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        if ($accountingClassId === null) {
            $account = ChartOfAccount::factory()->analytical()->create([
                'code' => 'TST.DEF.'.uniqid(),
                'account_group' => 4,
            ]);
            $accountingClassId = $account->id;
        }

        return OrderPayment::create([
            'store_id' => $store->id,
            'cost_center_id' => $cc->id,
            'accounting_class_id' => $accountingClassId,
            'supplier_id' => $supplier->id,
            'description' => 'Teste DRE projector',
            'total_value' => $totalValue,
            'date_payment' => '2026-03-15',
            'competence_date' => '2026-03-10',
            'payment_type' => 'PIX',
            'installments' => 1,
            'status' => $status,
            'created_by_user_id' => $user->id,
        ]);
    }
}
