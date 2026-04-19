<?php

namespace Tests\Unit;

use App\Models\Movement;
use App\Models\MovementType;
use App\Services\MovementInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private MovementInvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MovementInvoiceService;
        MovementType::factory()->sales()->create();
    }

    public function test_find_returns_null_when_no_movements_match(): void
    {
        $result = $this->service->find('Z999', '99999');

        $this->assertNull($result);
    }

    public function test_find_aggregates_all_items_of_an_invoice(): void
    {
        Movement::factory()->sale()->forInvoice('12345', 'Z001')->create([
            'realized_value' => 100, 'quantity' => 1, 'movement_date' => '2026-04-10',
            'cpf_customer' => '11111111111', 'cpf_consultant' => '22222222222',
        ]);
        Movement::factory()->sale()->forInvoice('12345', 'Z001')->create([
            'realized_value' => 200, 'quantity' => 2, 'movement_date' => '2026-04-10',
        ]);
        Movement::factory()->sale()->forInvoice('99999', 'Z001')->create();

        $result = $this->service->find('Z001', '12345');

        $this->assertNotNull($result);
        $this->assertCount(2, $result['items']);
        $this->assertSame(2, $result['totals']['items']);
        $this->assertSame(3.0, $result['totals']['quantity']);
        $this->assertSame(300.0, $result['totals']['realized_value']);
    }

    public function test_find_builds_header_from_first_item(): void
    {
        Movement::factory()->sale()->forInvoice('777', 'Z005')->create([
            'movement_date' => '2026-04-15',
            'movement_time' => '14:30:00',
            'cpf_customer' => '12345678901',
            'cpf_consultant' => '98765432109',
        ]);

        $result = $this->service->find('Z005', '777');

        $this->assertSame('Z005', $result['header']['store_code']);
        $this->assertSame('777', $result['header']['invoice_number']);
        $this->assertSame('2026-04-15', $result['header']['movement_date']);
        $this->assertSame('14:30:00', $result['header']['movement_time']);
        $this->assertSame('12345678901', $result['header']['cpf_customer']);
        $this->assertSame('98765432109', $result['header']['cpf_consultant']);
    }

    public function test_find_returns_negative_net_for_returns(): void
    {
        Movement::factory()->returnEntry()->forInvoice('500', 'Z002')->create([
            'realized_value' => 80, 'quantity' => 1,
        ]);

        $result = $this->service->find('Z002', '500');

        $this->assertSame(-80.0, $result['totals']['net_value']);
    }

    public function test_find_separates_invoices_by_store_even_with_same_number(): void
    {
        Movement::factory()->sale()->forInvoice('555', 'Z010')->create(['realized_value' => 50]);
        Movement::factory()->sale()->forInvoice('555', 'Z020')->create(['realized_value' => 70]);

        $a = $this->service->find('Z010', '555');
        $b = $this->service->find('Z020', '555');

        $this->assertSame(50.0, $a['totals']['realized_value']);
        $this->assertSame(70.0, $b['totals']['realized_value']);
    }
}
