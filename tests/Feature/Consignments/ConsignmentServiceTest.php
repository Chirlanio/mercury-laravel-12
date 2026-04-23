<?php

namespace Tests\Feature\Consignments;

use App\Enums\ConsignmentStatus;
use App\Enums\ConsignmentType;
use App\Models\Consignment;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\ConsignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobertura de ConsignmentService — CRUD, regra M8 (produto obrigatório
 * no catálogo) e M9 (bloqueio por destinatário com overdue).
 */
class ConsignmentServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Product $product;

    protected ProductVariant $variant;

    protected Employee $employee;

    protected ConsignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        app(\App\Services\CentralRoleResolver::class)->clearCache();

        $this->store = Store::factory()->create(['code' => 'Z421']);
        $employeeId = $this->createTestEmployee(['store_id' => $this->store->code]);
        $this->employee = Employee::findOrFail($employeeId);

        $this->product = Product::create([
            'reference' => 'REF-001',
            'description' => 'Produto Teste',
            'sale_price' => 199.90,
            'is_active' => true,
        ]);

        // barcode = concat ref+size (padrão CIGAM);
        // aux_reference = EAN-13 real (quando existir)
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'barcode' => 'REF-001U36',
            'aux_reference' => '1234567890123',
            'size_cigam_code' => 'U36',
            'is_active' => true,
        ]);

        $this->service = app(ConsignmentService::class);
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => ConsignmentType::CLIENTE->value,
            'store_id' => $this->store->id,
            'employee_id' => $this->employee->id,
            'recipient_name' => 'Maria Cliente',
            'recipient_document' => '123.456.789-09',
            'outbound_invoice_number' => '55001',
            'outbound_invoice_date' => '2026-04-23',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_variant_id' => $this->variant->id,
                    'reference' => 'REF-001',
                    'size_cigam_code' => 'U36',
                    'quantity' => 2,
                    'unit_value' => 199.90,
                ],
            ],
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Criação básica + totais agregados
    // ------------------------------------------------------------------

    public function test_create_with_valid_payload_returns_draft(): void
    {
        $consignment = $this->service->create($this->validPayload(), $this->adminUser);

        $this->assertInstanceOf(Consignment::class, $consignment);
        $this->assertEquals(ConsignmentStatus::DRAFT, $consignment->status);
        $this->assertEquals('12345678909', $consignment->recipient_document_clean);
        $this->assertEquals('MARIA CLIENTE', $consignment->recipient_name);
        $this->assertNotNull($consignment->uuid);
    }

    public function test_create_populates_aggregated_totals_from_items(): void
    {
        $consignment = $this->service->create($this->validPayload(), $this->adminUser);

        $this->assertEquals(2, $consignment->outbound_items_count);
        $this->assertEquals(399.80, (float) $consignment->outbound_total_value);
    }

    public function test_create_sets_expected_return_date_seven_days_later(): void
    {
        $consignment = $this->service->create(
            $this->validPayload(['outbound_invoice_date' => '2026-04-20']),
            $this->adminUser,
        );

        $this->assertEquals('2026-04-27', $consignment->expected_return_date->format('Y-m-d'));
        $this->assertEquals(7, $consignment->return_period_days);
    }

    public function test_create_with_issue_now_transitions_to_pending(): void
    {
        $consignment = $this->service->create(
            $this->validPayload(['issue_now' => true]),
            $this->adminUser,
        );

        $this->assertEquals(ConsignmentStatus::PENDING, $consignment->status);
        $this->assertNotNull($consignment->issued_at);
    }

    // ------------------------------------------------------------------
    // Regra M8 — produto obrigatório no catálogo
    // ------------------------------------------------------------------

    public function test_addItem_rejects_unknown_product(): void
    {
        $consignment = $this->service->create($this->validPayload([
            'items' => [],
        ]), $this->adminUser);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Produto não encontrado no catálogo');

        $this->service->addItem($consignment, [
            'reference' => 'REF-INEXISTENTE',
            'quantity' => 1,
            'unit_value' => 100.00,
        ]);
    }

    public function test_addItem_resolves_product_by_reference_when_product_id_missing(): void
    {
        $consignment = $this->service->create($this->validPayload([
            'items' => [],
        ]), $this->adminUser);

        $item = $this->service->addItem($consignment, [
            'reference' => 'REF-001',
            'size_cigam_code' => 'U36',
            'quantity' => 3,
            'unit_value' => 100.00,
        ]);

        $this->assertEquals($this->product->id, $item->product_id);
        $this->assertEquals($this->variant->id, $item->product_variant_id);
        $this->assertEquals(300.00, (float) $item->total_value);
    }

    public function test_addItem_resolves_product_by_barcode_ean(): void
    {
        $consignment = $this->service->create($this->validPayload([
            'items' => [],
        ]), $this->adminUser);

        $item = $this->service->addItem($consignment, [
            'barcode' => '1234567890123',
            'quantity' => 1,
            'unit_value' => 150.00,
        ]);

        $this->assertEquals($this->product->id, $item->product_id);
        $this->assertEquals($this->variant->id, $item->product_variant_id);
    }

    // ------------------------------------------------------------------
    // Regra por tipo — CLIENTE exige employee
    // ------------------------------------------------------------------

    public function test_create_cliente_without_employee_fails(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('consultor');

        $this->service->create($this->validPayload([
            'employee_id' => null,
        ]), $this->adminUser);
    }

    public function test_create_ecommerce_without_employee_ok(): void
    {
        $consignment = $this->service->create($this->validPayload([
            'type' => ConsignmentType::ECOMMERCE->value,
            'employee_id' => null,
        ]), $this->adminUser);

        $this->assertEquals(ConsignmentType::ECOMMERCE, $consignment->type);
        $this->assertNull($consignment->employee_id);
    }

    public function test_create_influencer_without_employee_ok(): void
    {
        $consignment = $this->service->create($this->validPayload([
            'type' => ConsignmentType::INFLUENCER->value,
            'employee_id' => null,
        ]), $this->adminUser);

        $this->assertEquals(ConsignmentType::INFLUENCER, $consignment->type);
    }

    // ------------------------------------------------------------------
    // CPF/CNPJ validation
    // ------------------------------------------------------------------

    public function test_create_rejects_invalid_document_length(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('11 (CPF) ou 14 (CNPJ)');

        $this->service->create($this->validPayload([
            'recipient_document' => '123',
        ]), $this->adminUser);
    }

    public function test_create_accepts_cnpj_14_digits(): void
    {
        $consignment = $this->service->create($this->validPayload([
            'recipient_document' => '12.345.678/0001-90',
        ]), $this->adminUser);

        $this->assertEquals('12345678000190', $consignment->recipient_document_clean);
    }

    // ------------------------------------------------------------------
    // Regra M9 — bloqueio por destinatário com overdue
    // ------------------------------------------------------------------

    public function test_create_blocks_when_recipient_has_overdue(): void
    {
        // Cria uma overdue primeiro
        Consignment::factory()
            ->overdue()
            ->forRecipientDocument('123.456.789-09')
            ->forStore($this->store)
            ->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('consignação(ões) em atraso');

        $this->service->create($this->validPayload(), $this->adminUser);
    }

    public function test_create_allows_override_lock_with_admin_permission(): void
    {
        Consignment::factory()
            ->overdue()
            ->forRecipientDocument('123.456.789-09')
            ->forStore($this->store)
            ->create();

        $consignment = $this->service->create(
            $this->validPayload(['override_lock_reason' => 'Cliente quitou por fora']),
            $this->adminUser,
        );

        $this->assertNotNull($consignment);
        // Histórico registra o override
        $this->assertEquals(1, $consignment->statusHistory()->count());
        $history = $consignment->statusHistory()->first();
        $this->assertStringContainsString('Override de bloqueio', $history->note);
        $this->assertTrue($history->context['override_lock'] ?? false);
    }

    public function test_create_blocks_override_for_user_without_permission(): void
    {
        Consignment::factory()
            ->overdue()
            ->forRecipientDocument('123.456.789-09')
            ->forStore($this->store)
            ->create();

        // regularUser não tem OVERRIDE_CONSIGNMENT_LOCK
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('não tem permissão');

        $this->service->create(
            $this->validPayload(['override_lock_reason' => 'Tentativa indevida']),
            $this->regularUser,
        );
    }

    public function test_create_ok_for_another_recipient_without_overdue(): void
    {
        Consignment::factory()
            ->overdue()
            ->forRecipientDocument('99988877766')
            ->forStore($this->store)
            ->create();

        // Outro destinatário sem overdue — sem bloqueio
        $consignment = $this->service->create(
            $this->validPayload(['recipient_document' => '123.456.789-09']),
            $this->adminUser,
        );

        $this->assertNotNull($consignment);
    }

    public function test_recipient_with_pending_not_overdue_doesnt_block(): void
    {
        // pending é status aberto mas NÃO blocking (M9 só bloqueia overdue)
        Consignment::factory()
            ->pending()
            ->forRecipientDocument('123.456.789-09')
            ->forStore($this->store)
            ->create();

        $consignment = $this->service->create($this->validPayload(), $this->adminUser);

        $this->assertNotNull($consignment);
    }

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------

    public function test_delete_marks_soft_deleted(): void
    {
        $consignment = $this->service->create($this->validPayload(), $this->adminUser);

        $this->service->delete($consignment, $this->adminUser, 'Erro de cadastro');

        $consignment->refresh();
        $this->assertNotNull($consignment->deleted_at);
        $this->assertEquals($this->adminUser->id, $consignment->deleted_by_user_id);
    }

    public function test_delete_blocked_for_user_without_permission(): void
    {
        $consignment = $this->service->create($this->validPayload(), $this->adminUser);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('permissão');

        // regularUser não tem DELETE_CONSIGNMENTS
        $this->service->delete($consignment, $this->regularUser);
    }
}
