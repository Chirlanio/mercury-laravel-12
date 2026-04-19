<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Services\ReturnOrderLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReturnOrderLookupTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected ReturnOrderLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        Store::factory()->create(['code' => 'Z441', 'name' => 'E-commerce']);
        Store::factory()->create(['code' => 'Z424', 'name' => 'Loja Física']);
        $this->service = app(ReturnOrderLookupService::class);
    }

    protected function insertSale(array $attrs): int
    {
        return DB::table('movements')->insertGetId(array_merge([
            'movement_date' => now()->toDateString(),
            'store_code' => 'Z441',
            'invoice_number' => 'NF',
            'movement_code' => 2,
            'cpf_consultant' => '98765432100',
            'ref_size' => 'REF1|M',
            'barcode' => '2000000000001',
            'sale_price' => 100,
            'cost_price' => 50,
            'realized_value' => 100,
            'discount_value' => 0,
            'quantity' => 1,
            'entry_exit' => 'S',
            'net_value' => 100,
            'net_quantity' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    public function test_returns_not_found_for_missing_invoice(): void
    {
        $result = $this->service->lookupInvoice('INEXISTENTE');

        $this->assertFalse($result['found']);
        $this->assertEmpty($result['items']);
        $this->assertEquals(0, $result['sale_total']);
    }

    public function test_defaults_to_z441_when_store_not_specified(): void
    {
        $this->insertSale(['invoice_number' => '12345', 'store_code' => 'Z441', 'realized_value' => 200]);
        $this->insertSale(['invoice_number' => '12345', 'store_code' => 'Z424', 'realized_value' => 500]);

        $result = $this->service->lookupInvoice('12345');

        $this->assertEquals('Z441', $result['store_code']);
        $this->assertEquals(200.0, $result['sale_total']);
    }

    public function test_sums_multiple_items_of_same_invoice(): void
    {
        $this->insertSale(['invoice_number' => 'NF-MULT', 'realized_value' => 100]);
        $this->insertSale(['invoice_number' => 'NF-MULT', 'realized_value' => 200]);
        $this->insertSale(['invoice_number' => 'NF-MULT', 'realized_value' => 50]);

        $result = $this->service->lookupInvoice('NF-MULT');

        $this->assertEquals(350.0, $result['sale_total']);
        $this->assertCount(3, $result['items']);
    }

    public function test_parses_ref_size_into_reference_and_size(): void
    {
        $this->insertSale([
            'invoice_number' => 'NF-REF',
            'ref_size' => 'ABC123|38',
            'realized_value' => 150,
        ]);

        $result = $this->service->lookupInvoice('NF-REF');

        $this->assertEquals('ABC123', $result['items'][0]['reference']);
        $this->assertEquals('38', $result['items'][0]['size']);
    }

    public function test_multiple_dates_returns_available_dates_sorted_desc(): void
    {
        $this->insertSale([
            'invoice_number' => 'REPEATED',
            'movement_date' => '2021-10-08',
            'realized_value' => 200,
        ]);
        $this->insertSale([
            'invoice_number' => 'REPEATED',
            'movement_date' => '2026-04-15',
            'realized_value' => 500,
        ]);

        $result = $this->service->lookupInvoice('REPEATED');

        $this->assertEquals('2026-04-15', $result['movement_date']);
        $this->assertEquals(500.0, $result['sale_total']);
        $this->assertCount(2, $result['available_dates']);
        $this->assertEquals('2026-04-15', $result['available_dates'][0]);
    }

    public function test_explicit_movement_date_overrides_default(): void
    {
        $this->insertSale([
            'invoice_number' => 'REPEATED',
            'movement_date' => '2021-10-08',
            'realized_value' => 200,
        ]);
        $this->insertSale([
            'invoice_number' => 'REPEATED',
            'movement_date' => '2026-04-15',
            'realized_value' => 500,
        ]);

        $result = $this->service->lookupInvoice('REPEATED', 'Z441', '2021-10-08');

        $this->assertEquals('2021-10-08', $result['movement_date']);
        $this->assertEquals(200.0, $result['sale_total']);
    }

    public function test_lookup_endpoint_returns_sale(): void
    {
        $this->insertSale(['invoice_number' => 'OK', 'realized_value' => 150]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('returns.lookup-invoice', ['invoice_number' => 'OK']));

        $response->assertStatus(200);
        $response->assertJsonPath('found', true);
        $response->assertJsonPath('sale_total', 150);
    }
}
