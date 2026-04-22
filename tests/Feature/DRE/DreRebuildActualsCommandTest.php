<?php

namespace Tests\Feature\DRE;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreActual;
use App\Models\OrderPayment;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o command `dre:rebuild-actuals` (playbook prompt 8).
 */
class DreRebuildActualsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_source_is_rejected(): void
    {
        $this->artisan('dre:rebuild-actuals', ['--source' => 'FOOBAR', '--force' => true])
            ->expectsOutputToContain('Fonte inválida')
            ->assertExitCode(2); // Command::INVALID
    }

    public function test_requires_confirmation_without_force(): void
    {
        $this->artisan('dre:rebuild-actuals', ['--source' => 'ORDER_PAYMENT'])
            ->expectsConfirmation('Continuar o rebuild da fonte "ORDER_PAYMENT"?', 'no')
            ->expectsOutputToContain('Cancelado')
            ->assertSuccessful();
    }

    public function test_force_skips_prompt_and_rebuilds_order_payment(): void
    {
        $account = ChartOfAccount::factory()->analytical()->create([
            'code' => 'CMD.RBLD.001',
            'account_group' => 4,
        ]);

        $op = $this->makeDoneOrderPayment($account->id);
        $this->assertDatabaseHas('dre_actuals', ['source_id' => $op->id]);

        // Zera manualmente para ver o rebuild reagir.
        DreActual::where('source', DreActual::SOURCE_ORDER_PAYMENT)->delete();
        $this->assertDatabaseMissing('dre_actuals', ['source_id' => $op->id]);

        $this->artisan('dre:rebuild-actuals', ['--source' => 'ORDER_PAYMENT', '--force' => true])
            ->expectsOutputToContain('Reprojetando OrderPayment')
            ->expectsOutputToContain('Projetadas: 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('dre_actuals', ['source_id' => $op->id]);
    }

    public function test_all_runs_both_projectors(): void
    {
        $this->artisan('dre:rebuild-actuals', ['--source' => 'all', '--force' => true])
            ->expectsOutputToContain('Reprojetando OrderPayment')
            ->expectsOutputToContain('Reprojetando Sale')
            ->assertSuccessful();
    }

    private function makeDoneOrderPayment(int $accountId): OrderPayment
    {
        $store = Store::factory()->create();
        $cc = CostCenter::factory()->create();
        $supplier = Supplier::create([
            'codigo_for' => 'TST-'.uniqid(),
            'razao_social' => 'Fornecedor CMD',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        return OrderPayment::create([
            'store_id' => $store->id,
            'cost_center_id' => $cc->id,
            'accounting_class_id' => $accountId,
            'supplier_id' => $supplier->id,
            'description' => 'Teste command',
            'total_value' => 250.00,
            'date_payment' => '2026-03-15',
            'competence_date' => '2026-03-10',
            'payment_type' => 'PIX',
            'installments' => 1,
            'status' => OrderPayment::STATUS_DONE,
            'created_by_user_id' => $user->id,
        ]);
    }
}
