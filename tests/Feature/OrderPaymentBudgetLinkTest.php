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

    // ------------------------------------------------------------------
    // Endpoint: accounting-classes-for-cost-center
    // ------------------------------------------------------------------

    public function test_endpoint_returns_accounting_classes_with_forecast_and_realized(): void
    {
        // Cria OP "realized" no budget
        OrderPayment::create([
            'description' => 'Conta de telefone março',
            'total_value' => 300,
            'date_payment' => '2026-03-10',
            'cost_center_id' => $this->cc->id,
            'accounting_class_id' => $this->ac->id,
            'budget_item_id' => $this->itemTelefonia2026->id,
            'status' => OrderPayment::STATUS_DOING,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('budgets.accounting-classes-for-cost-center', [
                'costCenter' => $this->cc->id,
                'year' => 2026,
            ]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['accounting_classes' => [['id', 'code', 'name', 'label', 'forecast_total', 'realized_total', 'available', 'utilization_pct', 'status']]]);

        $acs = $response->json('accounting_classes');
        $this->assertCount(1, $acs);
        $this->assertEquals($this->ac->id, $acs[0]['id']);
        $this->assertEquals(12000, $acs[0]['forecast_total']);
        $this->assertEquals(300, $acs[0]['realized_total']);
        $this->assertEquals(11700, $acs[0]['available']);
        $this->assertEquals('ok', $acs[0]['status']);
    }

    public function test_endpoint_returns_empty_when_no_budget_for_year(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('budgets.accounting-classes-for-cost-center', [
                'costCenter' => $this->cc->id,
                'year' => 2099,
            ]));

        $response->assertStatus(200);
        $response->assertJsonPath('accounting_classes', []);
    }

    public function test_endpoint_excludes_backlog_from_realized(): void
    {
        // OP backlog não entra no realized (OPs apenas solicitadas não comprometem)
        OrderPayment::create([
            'description' => 'Solicitação em aberto',
            'total_value' => 500,
            'date_payment' => '2026-03-10',
            'cost_center_id' => $this->cc->id,
            'accounting_class_id' => $this->ac->id,
            'budget_item_id' => $this->itemTelefonia2026->id,
            'status' => OrderPayment::STATUS_BACKLOG,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('budgets.accounting-classes-for-cost-center', [
                'costCenter' => $this->cc->id,
                'year' => 2026,
            ]));

        $response->assertJsonPath('accounting_classes.0.realized_total', 0);
    }

    // ------------------------------------------------------------------
    // Cascata Área → Gerencial: auto-derive cost_center_id da ManagementClass
    // ------------------------------------------------------------------

    public function test_store_derives_cost_center_id_from_management_class(): void
    {
        // MC já tem cost_center_id vinculado no setUp — envia só a MC e espera
        // o CC ser derivado pelo controller
        $mc = ManagementClass::create([
            'code' => 'MC-CASCADE', 'name' => 'Cascade test',
            'accepts_entries' => true,
            'cost_center_id' => $this->cc->id,
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('order-payments.store'), [
                ...$this->basePayload(),
                'management_class_id' => $mc->id,
                'accounting_class_id' => $this->ac->id,
                // cost_center_id NÃO enviado — deve vir da MC
            ])
            ->assertRedirect();

        $op = OrderPayment::latest('id')->first();
        $this->assertEquals($this->cc->id, $op->cost_center_id);
        $this->assertEquals($mc->id, $op->management_class_id);
        // Budget_item também deve bater porque CC foi derivado corretamente
        $this->assertEquals($this->itemTelefonia2026->id, $op->budget_item_id);
    }

    public function test_management_class_overrides_explicit_cost_center_id(): void
    {
        // Quando os dois vêm no payload e divergem, MC é a fonte autoritária
        $mc = ManagementClass::create([
            'code' => 'MC-OVERRIDE', 'name' => 'Override test',
            'accepts_entries' => true,
            'cost_center_id' => $this->cc->id,  // MC aponta para $this->cc
            'is_active' => true,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('order-payments.store'), [
                ...$this->basePayload(),
                'management_class_id' => $mc->id,
                'cost_center_id' => $this->ccOutro->id, // divergente → é sobrescrito
                'accounting_class_id' => $this->ac->id,
            ])
            ->assertRedirect();

        $op = OrderPayment::latest('id')->first();
        $this->assertEquals($this->cc->id, $op->cost_center_id);
    }

    public function test_endpoint_departments_returns_synthetic_parents_with_analytical_children(): void
    {
        // Seed real já populou 11 departamentos sintéticos 8.1.01..8.1.11
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('management-classes.departments'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'departments' => [
                ['id', 'code', 'name', 'classes' => [['id', 'code', 'name', 'cost_center']]],
            ],
        ]);

        $departments = $response->json('departments');
        $this->assertGreaterThanOrEqual(11, count($departments));

        // Todos devem ter código no padrão 8.1.XX (6 chars)
        foreach ($departments as $d) {
            $this->assertMatchesRegularExpression('/^8\.1\.\d{2}$/', $d['code']);
        }

        // Marketing (8.1.01) deve ter analíticas com CC vinculado
        $marketing = collect($departments)->firstWhere('code', '8.1.01');
        $this->assertNotNull($marketing);
        $this->assertNotEmpty($marketing['classes']);
        // Pelo menos uma analítica deve ter cost_center não-null
        $withCc = collect($marketing['classes'])->filter(fn ($c) => $c['cost_center'] !== null);
        $this->assertGreaterThan(0, $withCc->count());
    }
}
