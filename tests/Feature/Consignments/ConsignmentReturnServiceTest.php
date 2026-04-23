<?php

namespace Tests\Feature\Consignments;

use App\Enums\ConsignmentItemStatus;
use App\Enums\ConsignmentStatus;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\ConsignmentReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobertura do ConsignmentReturnService — regra M1 (itens de retorno =
 * itens da NF de saída) e transições automáticas pós-retorno.
 */
class ConsignmentReturnServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Product $product;

    protected ProductVariant $variant;

    protected Consignment $consignment;

    protected ConsignmentReturnService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        app(\App\Services\CentralRoleResolver::class)->clearCache();

        $this->store = Store::factory()->create(['code' => 'Z421']);

        $this->product = Product::create([
            'reference' => 'REF-001',
            'description' => 'Sandália teste',
            'sale_price' => 100.00,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'barcode' => '1234567890123',
            'size_cigam_code' => 'U36',
            'is_active' => true,
        ]);

        $this->consignment = Consignment::factory()
            ->pending()
            ->forStore($this->store)
            ->forRecipientDocument('12345678909')
            ->create();

        ConsignmentItem::factory()
            ->forConsignment($this->consignment)
            ->forProduct($this->product, $this->variant)
            ->quantity(5, 100.00)
            ->create();

        $this->consignment->refresh();
        app(\App\Services\ConsignmentService::class)->refreshTotals($this->consignment);
        $this->consignment->refresh();

        $this->service = app(ConsignmentReturnService::class);
    }

    protected function returnPayload(): array
    {
        return [
            'return_invoice_number' => '99001',
            'return_date' => '2026-04-23',
            'return_store_code' => 'Z421',
        ];
    }

    protected function firstItem(): ConsignmentItem
    {
        return $this->consignment->items()->first();
    }

    // ------------------------------------------------------------------
    // Caminho feliz — retorno total
    // ------------------------------------------------------------------

    public function test_register_full_return_completes_consignment(): void
    {
        $item = $this->firstItem();

        $return = $this->service->register(
            $this->consignment,
            $this->returnPayload(),
            [['consignment_item_id' => $item->id, 'quantity' => 5]],
            $this->adminUser,
        );

        $this->assertEquals(5, $return->returned_quantity);
        $this->assertEquals(500.00, (float) $return->returned_value);

        $item->refresh();
        $this->assertEquals(5, $item->returned_quantity);
        $this->assertEquals(ConsignmentItemStatus::RETURNED, $item->status);

        // Consignação deve ter transitado para COMPLETED
        $this->consignment->refresh();
        $this->assertEquals(ConsignmentStatus::COMPLETED, $this->consignment->status);
    }

    public function test_register_partial_return_sets_partially_returned(): void
    {
        $item = $this->firstItem();

        $this->service->register(
            $this->consignment,
            $this->returnPayload(),
            [['consignment_item_id' => $item->id, 'quantity' => 2]],
            $this->adminUser,
        );

        $item->refresh();
        $this->assertEquals(2, $item->returned_quantity);
        $this->assertEquals(ConsignmentItemStatus::PARTIAL, $item->status);

        $this->consignment->refresh();
        $this->assertEquals(ConsignmentStatus::PARTIALLY_RETURNED, $this->consignment->status);
    }

    public function test_multiple_partial_returns_can_complete_consignment(): void
    {
        $item = $this->firstItem();

        // 1º retorno: 3 peças
        $this->service->register(
            $this->consignment,
            array_merge($this->returnPayload(), ['return_invoice_number' => '99001']),
            [['consignment_item_id' => $item->id, 'quantity' => 3]],
            $this->adminUser,
        );

        $this->consignment->refresh();
        $this->assertEquals(ConsignmentStatus::PARTIALLY_RETURNED, $this->consignment->status);

        // 2º retorno: 2 peças restantes
        $this->service->register(
            $this->consignment,
            array_merge($this->returnPayload(), ['return_invoice_number' => '99002']),
            [['consignment_item_id' => $item->id, 'quantity' => 2]],
            $this->adminUser,
        );

        $this->consignment->refresh();
        $this->assertEquals(ConsignmentStatus::COMPLETED, $this->consignment->status);
        $this->assertEquals(2, $this->consignment->returns()->count());
    }

    // ------------------------------------------------------------------
    // REGRA M1 — itens de retorno devem ser da NF de saída
    // ------------------------------------------------------------------

    public function test_register_rejects_item_from_other_consignment(): void
    {
        // Outra consignação + outro item
        $otherConsignment = Consignment::factory()->pending()->forStore($this->store)->create();
        $otherItem = ConsignmentItem::factory()
            ->forConsignment($otherConsignment)
            ->forProduct($this->product, $this->variant)
            ->quantity(1)
            ->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Regra M1');

        $this->service->register(
            $this->consignment,
            $this->returnPayload(),
            [['consignment_item_id' => $otherItem->id, 'quantity' => 1]],
            $this->adminUser,
        );
    }

    public function test_register_rejects_quantity_exceeding_pending(): void
    {
        $item = $this->firstItem();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('excede o pendente');

        // Item tem 5 de saída, tentando devolver 6
        $this->service->register(
            $this->consignment,
            $this->returnPayload(),
            [['consignment_item_id' => $item->id, 'quantity' => 6]],
            $this->adminUser,
        );
    }

    public function test_register_rejects_quantity_exceeding_pending_after_previous_return(): void
    {
        $item = $this->firstItem();

        // Já devolveu 4 de 5
        $this->service->register(
            $this->consignment,
            array_merge($this->returnPayload(), ['return_invoice_number' => '99001']),
            [['consignment_item_id' => $item->id, 'quantity' => 4]],
            $this->adminUser,
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('excede o pendente');

        // Tenta devolver mais 2 (só tem 1 pendente)
        $this->service->register(
            $this->consignment->fresh(),
            array_merge($this->returnPayload(), ['return_invoice_number' => '99002']),
            [['consignment_item_id' => $item->id, 'quantity' => 2]],
            $this->adminUser,
        );
    }

    public function test_register_rejects_empty_items(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ao menos um item');

        $this->service->register(
            $this->consignment,
            $this->returnPayload(),
            [],
            $this->adminUser,
        );
    }

    public function test_register_rejects_invalid_quantity(): void
    {
        $item = $this->firstItem();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('obrigatórios');

        $this->service->register(
            $this->consignment,
            $this->returnPayload(),
            [['consignment_item_id' => $item->id, 'quantity' => 0]],
            $this->adminUser,
        );
    }

    // ------------------------------------------------------------------
    // Permissões e estados terminais
    // ------------------------------------------------------------------

    public function test_register_rejects_user_without_permission(): void
    {
        $item = $this->firstItem();

        // DRIVER não tem REGISTER_CONSIGNMENT_RETURN (sem permissões de
        // consignação no Role::DRIVER enum)
        $driverUser = \App\Models\User::factory()->create([
            'role' => \App\Enums\Role::DRIVER->value,
            'access_level_id' => 4,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('permissão');

        $this->service->register(
            $this->consignment,
            $this->returnPayload(),
            [['consignment_item_id' => $item->id, 'quantity' => 1]],
            $driverUser,
        );
    }

    public function test_register_rejects_completed_consignment(): void
    {
        $c = Consignment::factory()->completed()->forStore($this->store)->create();
        $item = ConsignmentItem::factory()
            ->forConsignment($c)
            ->forProduct($this->product, $this->variant)
            ->quantity(2)
            ->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('estado terminal');

        $this->service->register(
            $c->fresh(),
            $this->returnPayload(),
            [['consignment_item_id' => $item->id, 'quantity' => 1]],
            $this->adminUser,
        );
    }

    // ------------------------------------------------------------------
    // Totais agregados e pivô
    // ------------------------------------------------------------------

    public function test_register_updates_aggregated_totals(): void
    {
        $item = $this->firstItem();

        $this->service->register(
            $this->consignment,
            $this->returnPayload(),
            [['consignment_item_id' => $item->id, 'quantity' => 2]],
            $this->adminUser,
        );

        $this->consignment->refresh();
        $this->assertEquals(2, $this->consignment->returned_items_count);
        $this->assertEquals(200.00, (float) $this->consignment->returned_total_value);
    }

    public function test_register_creates_pivot_entries(): void
    {
        $item = $this->firstItem();

        $return = $this->service->register(
            $this->consignment,
            $this->returnPayload(),
            [['consignment_item_id' => $item->id, 'quantity' => 3]],
            $this->adminUser,
        );

        $this->assertEquals(1, $return->items()->count());
        $pivot = $return->items()->first();
        $this->assertEquals(3, $pivot->quantity);
        $this->assertEquals(100.00, (float) $pivot->unit_value);
        $this->assertEquals(300.00, (float) $pivot->subtotal);
    }
}
