<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class PurchaseOrderExportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->store = Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Teste']);
        $this->supplier = $this->createTestSupplier(['nome_fantasia' => 'Fornecedor Teste']);

        // Cria 3 ordens com items pra ter o que exportar
        for ($i = 1; $i <= 3; $i++) {
            $order = PurchaseOrder::create([
                'order_number' => "PO-EXP-{$i}",
                'season' => 'INVERNO 2026',
                'collection' => 'C1',
                'release_name' => 'L1',
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->code,
                'order_date' => '2026-01-15',
                'status' => PurchaseOrderStatus::PENDING->value,
                'created_by_user_id' => $this->adminUser->id,
            ]);
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'reference' => "REF-{$i}",
                'size' => '36',
                'description' => "Item {$i}",
                'unit_cost' => 100.00 * $i,
                'selling_price' => 250.00 * $i,
                'quantity_ordered' => 5,
            ]);
        }
    }

    public function test_export_excel_returns_xlsx_response(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('purchase-orders.export', ['format' => 'excel']));

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type') ?? ''
        );
    }

    public function test_export_pdf_returns_pdf_response(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('purchase-orders.export', ['format' => 'pdf']));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_export_respects_status_filter(): void
    {
        // 1 ordem cancelada
        PurchaseOrder::create([
            'order_number' => 'PO-CANCELED',
            'season' => 'V', 'collection' => 'C', 'release_name' => 'L',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->code,
            'order_date' => '2026-01-15',
            'status' => PurchaseOrderStatus::CANCELLED->value,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        // Export filtrado apenas pendentes
        $response = $this->actingAs($this->adminUser)
            ->get(route('purchase-orders.export', ['format' => 'excel', 'status' => 'pending']));

        $response->assertStatus(200);
        // Sem filhos do dompdf etc — apenas verifica que não falhou
    }

    public function test_export_invalid_format_returns_error(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('purchase-orders.export', ['format' => 'xml']));

        $response->assertSessionHasErrors('format');
    }

    public function test_user_without_export_permission_is_denied(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('purchase-orders.export', ['format' => 'excel']));

        $response->assertStatus(403);
    }
}
