<?php

namespace Tests\Feature;

use App\Models\AccountingClass;
use App\Models\BudgetItem;
use App\Models\BudgetUpload;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\OrderPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre o auto-resolve do budget_item_id em OrderPayment — fundação da
 * integração com o módulo Budgets (C1 do roadmap).
 *
 * Regra: OP recebe budget_item_id automaticamente quando CC + AC batem com
 * um BudgetItem de um BudgetUpload ativo no ano da competência (ou do
 * date_payment quando competência omitida).
 */
class OrderPaymentBudgetLinkTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected AccountingClass $ac;

    protected AccountingClass $acOutra;

    protected CostCenter $cc;

    protected CostCenter $ccOutro;

    protected BudgetUpload $budget2026;

    protected BudgetItem $itemTelefonia2026;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->ac = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail(); // Telefonia
        $this->acOutra = AccountingClass::where('code', '4.2.1.04.00083')->firstOrFail(); // Outras Despesas

        $this->cc = CostCenter::create([
            'code' => 'CC-OP-TEST', 'name' => 'CC Teste OP',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->ccOutro = CostCenter::create([
            'code' => 'CC-OP-OUTRO', 'name' => 'CC Outro',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);

        $mc = ManagementClass::create([
            'code' => 'MC-OP-TEST', 'name' => 'Gerencial OP',
            'accepts_entries' => true, 'accounting_class_id' => $this->ac->id,
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->budget2026 = BudgetUpload::create([
            'year' => 2026, 'scope_label' => 'BPLink', 'version_label' => '1.0',
            'major_version' => 1, 'minor_version' => 0, 'upload_type' => 'novo',
            'original_filename' => 't.xlsx', 'stored_path' => 'budgets/2026/t.xlsx',
            'file_size_bytes' => 1, 'is_active' => true, 'total_year' => 12000,
            'items_count' => 1,
            'created_by_user_id' => $this->adminUser->id,
            'updated_by_user_id' => $this->adminUser->id,
        ]);

        $this->itemTelefonia2026 = BudgetItem::create([
            'budget_upload_id' => $this->budget2026->id,
            'accounting_class_id' => $this->ac->id,
            'management_class_id' => $mc->id,
            'cost_center_id' => $this->cc->id,
            'month_01_value' => 1000, 'month_02_value' => 1000, 'month_03_value' => 1000,
            'month_04_value' => 1000, 'month_05_value' => 1000, 'month_06_value' => 1000,
            'month_07_value' => 1000, 'month_08_value' => 1000, 'month_09_value' => 1000,
            'month_10_value' => 1000, 'month_11_value' => 1000, 'month_12_value' => 1000,
            'year_total' => 12000,
        ]);
    }

    protected function basePayload(): array
    {
        return [
            'description' => 'OP teste',
            'total_value' => 500,
            'date_payment' => '2026-04-15',
        ];
    }

    public function test_store_resolves_budget_item_when_cc_and_ac_match_active_budget(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('order-payments.store'), [
                ...$this->basePayload(),
                'cost_center_id' => $this->cc->id,
                'accounting_class_id' => $this->ac->id,
            ])
            ->assertRedirect();

        $op = OrderPayment::latest('id')->first();
        $this->assertEquals($this->itemTelefonia2026->id, $op->budget_item_id);
    }

    public function test_store_leaves_budget_item_null_when_accounting_class_missing(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('order-payments.store'), [
                ...$this->basePayload(),
                'cost_center_id' => $this->cc->id,
                // accounting_class_id ausente
            ])
            ->assertRedirect();

        $op = OrderPayment::latest('id')->first();
        $this->assertNull($op->budget_item_id);
    }

    public function test_store_leaves_budget_item_null_when_no_active_budget_for_year(): void
    {
        // date_payment em 2027 — sem budget ativo para esse ano
        $this->actingAs($this->adminUser)
            ->post(route('order-payments.store'), [
                ...$this->basePayload(),
                'date_payment' => '2027-04-15',
                'cost_center_id' => $this->cc->id,
                'accounting_class_id' => $this->ac->id,
            ])
            ->assertRedirect();

        $op = OrderPayment::latest('id')->first();
        $this->assertNull($op->budget_item_id);
    }

    public function test_store_leaves_budget_item_null_when_cc_does_not_match(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('order-payments.store'), [
                ...$this->basePayload(),
                'cost_center_id' => $this->ccOutro->id,  // CC sem budget cadastrado
                'accounting_class_id' => $this->ac->id,
            ])
            ->assertRedirect();

        $op = OrderPayment::latest('id')->first();
        $this->assertNull($op->budget_item_id);
    }

    public function test_store_uses_competence_date_year_over_date_payment(): void
    {
        // Caixa em 2027, competência em 2026 — deve pegar o budget de 2026
        $this->actingAs($this->adminUser)
            ->post(route('order-payments.store'), [
                ...$this->basePayload(),
                'date_payment' => '2027-01-10',
                'competence_date' => '2026-12-20',
                'cost_center_id' => $this->cc->id,
                'accounting_class_id' => $this->ac->id,
            ])
            ->assertRedirect();

        $op = OrderPayment::latest('id')->first();
        $this->assertEquals($this->itemTelefonia2026->id, $op->budget_item_id);
    }

    public function test_update_recalculates_budget_item_when_accounting_class_changes(): void
    {
        // Cria com AC que bate
        $this->actingAs($this->adminUser)
            ->post(route('order-payments.store'), [
                ...$this->basePayload(),
                'cost_center_id' => $this->cc->id,
                'accounting_class_id' => $this->ac->id,
            ]);
        $op = OrderPayment::latest('id')->first();
        $this->assertEquals($this->itemTelefonia2026->id, $op->budget_item_id);

        // Troca AC para uma que não tem budget
        $this->actingAs($this->adminUser)
            ->put(route('order-payments.update', $op), [
                ...$this->basePayload(),
                'cost_center_id' => $this->cc->id,
                'accounting_class_id' => $this->acOutra->id,
            ])
            ->assertRedirect();

        $op->refresh();
        $this->assertNull($op->budget_item_id);
    }

    public function test_update_recalculates_budget_item_when_cost_center_changes(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('order-payments.store'), [
                ...$this->basePayload(),
                'cost_center_id' => $this->cc->id,
                'accounting_class_id' => $this->ac->id,
            ]);
        $op = OrderPayment::latest('id')->first();
        $this->assertEquals($this->itemTelefonia2026->id, $op->budget_item_id);

        // Troca CC
        $this->actingAs($this->adminUser)
            ->put(route('order-payments.update', $op), [
                ...$this->basePayload(),
                'cost_center_id' => $this->ccOutro->id,
                'accounting_class_id' => $this->ac->id,
            ])
            ->assertRedirect();

        $op->refresh();
        $this->assertNull($op->budget_item_id);
    }

    public function test_store_skips_budget_item_when_upload_is_inactive(): void
    {
        $this->budget2026->update(['is_active' => false]);

        $this->actingAs($this->adminUser)
            ->post(route('order-payments.store'), [
                ...$this->basePayload(),
                'cost_center_id' => $this->cc->id,
                'accounting_class_id' => $this->ac->id,
            ])
            ->assertRedirect();

        $op = OrderPayment::latest('id')->first();
        $this->assertNull($op->budget_item_id);
    }
}
