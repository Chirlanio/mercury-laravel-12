<?php

namespace Tests\Feature;

use App\Models\AccountingClass;
use App\Models\CostCenter;
use App\Models\ManagementClass;
use App\Models\OrderPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobre a regra de acesso por role em OrderPaymentController::update():
 * SUPER_ADMIN/ADMIN/SUPPORT editam todos os campos, USER só os não críticos.
 *
 * Campos críticos: total_value, advance*, cost_center_id, accounting_class_id,
 * management_class_id, date_payment, competence_date, launch_number, supplier_id.
 */
class OrderPaymentCriticalFieldsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected OrderPayment $op;

    protected AccountingClass $acOriginal;

    protected AccountingClass $acOutra;

    protected CostCenter $ccOriginal;

    protected CostCenter $ccOutro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        // O CentralRoleResolver cacheia permissões por 5min. Se o Role enum
        // foi editado após o cache ser populado (ex: adicionamos EDIT_ORDER_PAYMENTS
        // ao USER), a lista antiga permanece. Limpar é barato nos testes.
        app(\App\Services\CentralRoleResolver::class)->clearCache();

        $this->acOriginal = AccountingClass::where('code', '4.2.1.04.00032')->firstOrFail();
        $this->acOutra = AccountingClass::where('code', '4.2.1.04.00083')->firstOrFail();

        $this->ccOriginal = CostCenter::create([
            'code' => 'CC-CRIT-A', 'name' => 'CC Original',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);
        $this->ccOutro = CostCenter::create([
            'code' => 'CC-CRIT-B', 'name' => 'CC Alternativo',
            'is_active' => true, 'created_by_user_id' => $this->adminUser->id,
        ]);

        $this->op = OrderPayment::create([
            'description' => 'OP original',
            'total_value' => 1000,
            'date_payment' => '2026-05-15',
            'competence_date' => '2026-05-01',
            'cost_center_id' => $this->ccOriginal->id,
            'accounting_class_id' => $this->acOriginal->id,
            'number_nf' => '100',
            'launch_number' => 'L-100',
            'advance' => true,
            'advance_amount' => 200,
            'status' => OrderPayment::STATUS_DOING,
            'created_by_user_id' => $this->adminUser->id,
        ]);
    }

    protected function fullUpdatePayload(): array
    {
        return [
            // Não críticos
            'description' => 'Descrição alterada',
            'number_nf' => '999',
            'observations' => 'Observação nova',
            // Críticos — tentativa de tampering
            'total_value' => 9999,
            'date_payment' => '2027-01-15',
            'competence_date' => '2027-01-01',
            'cost_center_id' => $this->ccOutro->id,
            'accounting_class_id' => $this->acOutra->id,
            'launch_number' => 'L-999',
            'advance' => false,
            'advance_amount' => 0,
        ];
    }

    public function test_admin_can_edit_all_fields_including_critical(): void
    {
        $this->actingAs($this->adminUser)
            ->put(route('order-payments.update', $this->op), $this->fullUpdatePayload())
            ->assertRedirect();

        $this->op->refresh();

        // Não críticos: atualizados
        $this->assertEquals('Descrição alterada', $this->op->description);
        $this->assertEquals('999', $this->op->number_nf);
        $this->assertEquals('Observação nova', $this->op->observations);

        // Críticos: admin editou com sucesso
        $this->assertEquals(9999, $this->op->total_value);
        $this->assertEquals('2027-01-15', $this->op->date_payment->format('Y-m-d'));
        $this->assertEquals($this->ccOutro->id, $this->op->cost_center_id);
        $this->assertEquals($this->acOutra->id, $this->op->accounting_class_id);
        $this->assertEquals('L-999', $this->op->launch_number);
    }

    public function test_support_can_edit_critical_fields(): void
    {
        $this->actingAs($this->supportUser)
            ->put(route('order-payments.update', $this->op), $this->fullUpdatePayload())
            ->assertRedirect();

        $this->op->refresh();

        // Support tem a mesma capacidade de edição crítica que admin
        $this->assertEquals(9999, $this->op->total_value);
        $this->assertEquals($this->ccOutro->id, $this->op->cost_center_id);
        $this->assertEquals($this->acOutra->id, $this->op->accounting_class_id);
    }

    public function test_regular_user_cannot_edit_critical_fields_but_can_edit_others(): void
    {
        $this->actingAs($this->regularUser)
            ->put(route('order-payments.update', $this->op), $this->fullUpdatePayload())
            ->assertRedirect();

        $this->op->refresh();

        // Não críticos: atualizados normalmente
        $this->assertEquals('Descrição alterada', $this->op->description);
        $this->assertEquals('999', $this->op->number_nf); // number_nf NÃO é crítico
        $this->assertEquals('Observação nova', $this->op->observations);

        // Críticos: preservados com valores originais (tampering ignorado)
        $this->assertEquals(1000, (int) $this->op->total_value);
        $this->assertEquals('2026-05-15', $this->op->date_payment->format('Y-m-d'));
        $this->assertEquals('2026-05-01', $this->op->competence_date->format('Y-m-d'));
        $this->assertEquals($this->ccOriginal->id, $this->op->cost_center_id);
        $this->assertEquals($this->acOriginal->id, $this->op->accounting_class_id);
        $this->assertEquals('L-100', $this->op->launch_number);
        $this->assertTrue((bool) $this->op->advance);
        $this->assertEquals(200, (int) $this->op->advance_amount);
    }

    public function test_regular_user_can_update_without_sending_critical_fields(): void
    {
        // User regular pode enviar só os não críticos — não deve gerar 422 por
        // total_value/date_payment ausentes.
        $this->actingAs($this->regularUser)
            ->put(route('order-payments.update', $this->op), [
                'description' => 'Só descrição',
                'bank_name' => 'Itaú',
            ])
            ->assertRedirect();

        $this->op->refresh();
        $this->assertEquals('Só descrição', $this->op->description);
        $this->assertEquals('Itaú', $this->op->bank_name);
        // Críticos preservados
        $this->assertEquals(1000, (int) $this->op->total_value);
    }

    public function test_number_nf_is_NOT_critical(): void
    {
        // NF pode ser preenchida depois pelo user regular quando a nota chega
        $this->op->update(['number_nf' => null]);

        $this->actingAs($this->regularUser)
            ->put(route('order-payments.update', $this->op), [
                'description' => $this->op->description,
                'number_nf' => 'NF-POSTERIOR-123',
            ])
            ->assertRedirect();

        $this->op->refresh();
        $this->assertEquals('NF-POSTERIOR-123', $this->op->number_nf);
    }
}
