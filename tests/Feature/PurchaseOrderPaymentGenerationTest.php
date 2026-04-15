<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\OrderPayment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\PaymentTermsParser;
use App\Services\PurchaseOrderPaymentGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PurchaseOrderPaymentGenerationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
        $this->supplier = $this->createTestSupplier();
    }

    // ------------------------------------------------------------------
    // PaymentTermsParser
    // ------------------------------------------------------------------

    public function test_parser_handles_slash_format(): void
    {
        $parser = new PaymentTermsParser();
        $this->assertEquals([30, 60, 90], $parser->parse('30/60/90'));
    }

    public function test_parser_handles_long_format(): void
    {
        $parser = new PaymentTermsParser();
        $this->assertEquals([30, 60, 90, 105, 120], $parser->parse('30/60/90/105/120'));
    }

    public function test_parser_handles_a_vista_variants(): void
    {
        $parser = new PaymentTermsParser();
        $this->assertEquals([0], $parser->parse('À vista'));
        $this->assertEquals([0], $parser->parse('a vista'));
        $this->assertEquals([0], $parser->parse('AVISTA'));
    }

    public function test_parser_handles_dias_suffix(): void
    {
        $parser = new PaymentTermsParser();
        $this->assertEquals([30], $parser->parse('30 dias'));
        $this->assertEquals([30, 60], $parser->parse('30d/60d'));
    }

    public function test_parser_returns_empty_for_invalid(): void
    {
        $parser = new PaymentTermsParser();
        $this->assertEquals([], $parser->parse(null));
        $this->assertEquals([], $parser->parse(''));
        $this->assertEquals([], $parser->parse('xyz'));
    }

    public function test_parser_split_amount_distributes_evenly(): void
    {
        $parser = new PaymentTermsParser();
        $result = $parser->splitAmount(300.00, 3);
        $this->assertEquals([100.00, 100.00, 100.00], $result);
    }

    public function test_parser_split_amount_absorbs_remainder_in_last(): void
    {
        $parser = new PaymentTermsParser();
        $result = $parser->splitAmount(100.00, 3);
        // 33.33 * 2 = 66.66 + 33.34 = 100.00
        $this->assertEquals([33.33, 33.33, 33.34], $result);
    }

    // ------------------------------------------------------------------
    // PurchaseOrderPaymentGenerator
    // ------------------------------------------------------------------

    public function test_skips_when_auto_generate_disabled(): void
    {
        $order = $this->createTestOrderWithItem(autoGenerate: false);

        $generator = app(PurchaseOrderPaymentGenerator::class);
        $result = $generator->generateForOrder($order);

        $this->assertEquals(0, $result['generated']);
        $this->assertStringContainsString('false', $result['skipped_reason']);
        $this->assertEquals(0, OrderPayment::where('purchase_order_id', $order->id)->count());
    }

    public function test_generates_one_payment_per_term(): void
    {
        $order = $this->createTestOrderWithItem(autoGenerate: true, terms: '30/60/90');

        $generator = app(PurchaseOrderPaymentGenerator::class);
        $result = $generator->generateForOrder($order);

        $this->assertEquals(3, $result['generated']);
        $payments = OrderPayment::where('purchase_order_id', $order->id)
            ->orderBy('date_payment')
            ->get();
        $this->assertCount(3, $payments);

        // Total deve bater (item: 10 * 100 = 1000)
        $this->assertEquals(1000.00, $payments->sum('total_value'));

        // Datas crescentes (30, 60, 90 dias)
        $this->assertTrue($payments[0]->date_payment->lt($payments[1]->date_payment));
        $this->assertTrue($payments[1]->date_payment->lt($payments[2]->date_payment));

        // Cada parcela referencia a ordem
        foreach ($payments as $p) {
            $this->assertEquals($order->id, $p->purchase_order_id);
            $this->assertEquals($order->supplier_id, $p->supplier_id);
            $this->assertEquals('backlog', $p->status);
            $this->assertStringContainsString($order->order_number, $p->description);
        }
    }

    public function test_is_idempotent(): void
    {
        $order = $this->createTestOrderWithItem(autoGenerate: true, terms: '30/60');

        $generator = app(PurchaseOrderPaymentGenerator::class);
        $generator->generateForOrder($order);
        $result2 = $generator->generateForOrder($order);

        $this->assertEquals(0, $result2['generated']);
        $this->assertStringContainsString('já existem', $result2['skipped_reason']);
        $this->assertEquals(2, OrderPayment::where('purchase_order_id', $order->id)->count());
    }

    public function test_skips_when_no_terms(): void
    {
        $order = $this->createTestOrderWithItem(autoGenerate: true, terms: null);

        $generator = app(PurchaseOrderPaymentGenerator::class);
        $result = $generator->generateForOrder($order);

        $this->assertEquals(0, $result['generated']);
        $this->assertStringContainsString('payment_terms_raw', $result['skipped_reason']);
    }

    public function test_skips_when_order_has_no_items(): void
    {
        $order = PurchaseOrder::create([
            'order_number' => 'PO-EMPTY',
            'season' => 'V1', 'collection' => 'C1', 'release_name' => 'L1',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->code,
            'order_date' => '2026-01-15',
            'status' => PurchaseOrderStatus::PENDING->value,
            'auto_generate_payments' => true,
            'payment_terms_raw' => '30/60',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $generator = app(PurchaseOrderPaymentGenerator::class);
        $result = $generator->generateForOrder($order);

        $this->assertEquals(0, $result['generated']);
        $this->assertStringContainsString('custo zero', $result['skipped_reason']);
    }

    // ------------------------------------------------------------------
    // Integração com state machine
    // ------------------------------------------------------------------

    public function test_transition_to_invoiced_triggers_payment_generation(): void
    {
        $order = $this->createTestOrderWithItem(autoGenerate: true, terms: '30/60/90');

        $response = $this->actingAs($this->adminUser)->post(
            route('purchase-orders.transition', $order->id),
            ['to_status' => PurchaseOrderStatus::INVOICED->value]
        );

        $response->assertRedirect();
        $this->assertEquals('invoiced', $order->fresh()->status->value);
        $this->assertEquals(3, OrderPayment::where('purchase_order_id', $order->id)->count());
    }

    public function test_transition_to_invoiced_does_nothing_when_flag_off(): void
    {
        $order = $this->createTestOrderWithItem(autoGenerate: false, terms: '30/60/90');

        $this->actingAs($this->adminUser)->post(
            route('purchase-orders.transition', $order->id),
            ['to_status' => PurchaseOrderStatus::INVOICED->value]
        );

        $this->assertEquals(0, OrderPayment::where('purchase_order_id', $order->id)->count());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function createTestOrderWithItem(bool $autoGenerate, ?string $terms = '30/60/90'): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'order_number' => 'PO-' . uniqid(),
            'season' => 'V1', 'collection' => 'C1', 'release_name' => 'L1',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->code,
            'order_date' => '2026-01-15',
            'status' => PurchaseOrderStatus::PENDING->value,
            'auto_generate_payments' => $autoGenerate,
            'payment_terms_raw' => $terms,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'reference' => 'REF-X',
            'size' => '36',
            'description' => 'Item teste',
            'unit_cost' => 100.00,
            'selling_price' => 250.00,
            'quantity_ordered' => 10,
        ]);

        return $order->fresh('items');
    }
}
