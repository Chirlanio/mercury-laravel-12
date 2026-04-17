<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Services\ReversalLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ReversalLookupTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected ReversalLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        Store::factory()->create(['code' => 'Z424', 'name' => 'Loja']);
        Store::factory()->create(['code' => 'Z425', 'name' => 'Outra Loja']);
        $this->service = app(ReversalLookupService::class);
    }

    protected function insertSale(array $attrs): int
    {
        return DB::table('movements')->insertGetId(array_merge([
            'movement_date' => now()->toDateString(),
            'store_code' => 'Z424',
            'invoice_number' => 'NF',
            'movement_code' => 2,
            'cpf_consultant' => '98765432100',
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
        $result = $this->service->lookupInvoice('INEXISTENTE', 'Z424');

        $this->assertFalse($result['found']);
        $this->assertEmpty($result['items']);
        $this->assertEquals(0, $result['sale_total']);
    }

    public function test_returns_single_sale_when_invoice_unique(): void
    {
        $this->insertSale(['invoice_number' => '12345', 'realized_value' => 300]);

        $result = $this->service->lookupInvoice('12345', 'Z424');

        $this->assertTrue($result['found']);
        $this->assertEquals('Z424', $result['store_code']);
        $this->assertEquals(300.0, $result['sale_total']);
        $this->assertCount(1, $result['items']);
    }

    public function test_sums_multiple_items_of_same_invoice(): void
    {
        $this->insertSale(['invoice_number' => 'NF-MULT', 'realized_value' => 100]);
        $this->insertSale(['invoice_number' => 'NF-MULT', 'realized_value' => 200]);
        $this->insertSale(['invoice_number' => 'NF-MULT', 'realized_value' => 50]);

        $result = $this->service->lookupInvoice('NF-MULT', 'Z424');

        $this->assertEquals(350.0, $result['sale_total']);
        $this->assertCount(3, $result['items']);
    }

    public function test_filters_by_store_code(): void
    {
        $this->insertSale(['invoice_number' => 'REP', 'store_code' => 'Z424', 'realized_value' => 100]);
        $this->insertSale(['invoice_number' => 'REP', 'store_code' => 'Z425', 'realized_value' => 300]);

        $result = $this->service->lookupInvoice('REP', 'Z424');

        $this->assertEquals('Z424', $result['store_code']);
        $this->assertEquals(100.0, $result['sale_total']);
    }

    public function test_multiple_dates_returns_available_dates_sorted_desc(): void
    {
        // Invoice com mesma numeração em anos diferentes (real caso v1)
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

        $result = $this->service->lookupInvoice('REPEATED', 'Z424');

        // Default escolhe a MAIS RECENTE
        $this->assertEquals('2026-04-15', $result['movement_date']);
        $this->assertEquals(500.0, $result['sale_total']);
        $this->assertCount(2, $result['available_dates']);
        // Primeira da lista deve ser a mais recente
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

        $result = $this->service->lookupInvoice('REPEATED', 'Z424', '2021-10-08');

        $this->assertEquals('2021-10-08', $result['movement_date']);
        $this->assertEquals(200.0, $result['sale_total']);
    }

    public function test_lookup_endpoint_requires_store_when_not_scoped(): void
    {
        $this->insertSale(['invoice_number' => 'X', 'realized_value' => 100]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('reversals.lookup-invoice', ['invoice_number' => 'X']));

        $response->assertStatus(200);
        $response->assertJsonPath('found', false);
        $response->assertJsonPath('error', 'Informe a loja para buscar a NF/cupom.');
    }

    public function test_lookup_endpoint_returns_sale_when_store_provided(): void
    {
        $this->insertSale(['invoice_number' => 'OK', 'realized_value' => 150]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('reversals.lookup-invoice', [
                'invoice_number' => 'OK',
                'store_code' => 'Z424',
            ]));

        $response->assertStatus(200);
        $response->assertJsonPath('found', true);
        $response->assertJsonPath('sale_total', 150);
    }
}
