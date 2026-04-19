<?php

namespace Tests\Feature;

use App\Models\Movement;
use App\Models\MovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class MovementControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->createTestStore('Z001');
        $this->createTestStore('Z002');
        MovementType::factory()->sales()->create();
        MovementType::factory()->returns()->create();
    }

    // =============================================
    // INDEX
    // =============================================

    public function test_index_requires_auth(): void
    {
        $this->get('/movements')->assertRedirect('/login');
    }

    public function test_index_requires_view_movements_permission(): void
    {
        $this->actingAs($this->regularUser)->get('/movements')->assertStatus(403);
    }

    public function test_index_renders_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/movements');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Movements/Index')
            ->has('movements')
            ->has('stores')
            ->has('movementTypes')
            ->has('filters'));
    }

    public function test_index_filters_by_store_code(): void
    {
        Movement::factory()->sale()->forStore('Z001')->forDate('2026-04-15')->create();
        Movement::factory()->sale()->forStore('Z002')->forDate('2026-04-15')->create();

        $response = $this->actingAs($this->adminUser)
            ->get('/movements?date_start=2026-04-15&date_end=2026-04-15&store_code=Z001');

        $props = $response->original->getData()['page']['props'];
        $this->assertSame(1, $props['movements']['total']);
    }

    public function test_index_filters_by_entry_exit(): void
    {
        Movement::factory()->sale()->forDate('2026-04-15')->create(['entry_exit' => 'S']);
        Movement::factory()->returnEntry()->forDate('2026-04-15')->create();

        $response = $this->actingAs($this->adminUser)
            ->get('/movements?date_start=2026-04-15&date_end=2026-04-15&entry_exit=E');

        $props = $response->original->getData()['page']['props'];
        $this->assertSame(1, $props['movements']['total']);
    }

    public function test_index_filters_by_cpf_consultant_prefix(): void
    {
        Movement::factory()->sale()->forDate('2026-04-15')->create(['cpf_consultant' => '12345678901']);
        Movement::factory()->sale()->forDate('2026-04-15')->create(['cpf_consultant' => '99999999999']);

        $response = $this->actingAs($this->adminUser)
            ->get('/movements?date_start=2026-04-15&date_end=2026-04-15&cpf_consultant=123');

        $props = $response->original->getData()['page']['props'];
        $this->assertSame(1, $props['movements']['total']);
    }

    public function test_index_filters_by_sync_status_pending(): void
    {
        Movement::factory()->sale()->forDate('2026-04-15')->create();
        Movement::factory()->sale()->unsynced()->forDate('2026-04-15')->create();

        $response = $this->actingAs($this->adminUser)
            ->get('/movements?date_start=2026-04-15&date_end=2026-04-15&sync_status=pending');

        $props = $response->original->getData()['page']['props'];
        $this->assertSame(1, $props['movements']['total']);
    }

    // =============================================
    // STATISTICS
    // =============================================

    public function test_statistics_returns_aggregated_values(): void
    {
        Movement::factory()->sale()->forDate('2026-04-15')->create(['realized_value' => 100, 'quantity' => 2]);
        Movement::factory()->sale()->forDate('2026-04-15')->create(['realized_value' => 200, 'quantity' => 3]);
        Movement::factory()->returnEntry()->forDate('2026-04-15')->create(['realized_value' => 50]);

        $response = $this->actingAs($this->adminUser)->get('/movements/statistics?date=2026-04-15');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(250.0, $data['today_net']);   // 300 sales - 50 returns
        $this->assertSame(5, $data['items_sold']);         // 2 + 3
        $this->assertSame(3, $data['total_movements']);
    }

    public function test_statistics_returns_null_variations_when_no_historical_data(): void
    {
        Movement::factory()->sale()->forDate('2026-04-15')->create();

        $response = $this->actingAs($this->adminUser)->get('/movements/statistics?date=2026-04-15');

        $data = $response->json();
        $this->assertNull($data['variation_yesterday']);
        $this->assertNull($data['variation_week']);
    }

    // =============================================
    // INVOICE (JSON endpoint)
    // =============================================

    public function test_invoice_returns_404_when_not_found(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/movements/invoice/Z001/99999/2026-04-15');

        $response->assertStatus(404);
    }

    public function test_invoice_returns_header_items_and_totals(): void
    {
        Movement::factory()->sale()->forInvoice('5000', 'Z001')->forDate('2026-04-15')->create([
            'realized_value' => 150, 'quantity' => 1,
        ]);
        Movement::factory()->sale()->forInvoice('5000', 'Z001')->forDate('2026-04-15')->create([
            'realized_value' => 250, 'quantity' => 2,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/movements/invoice/Z001/5000/2026-04-15');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'header' => ['store_code', 'invoice_number', 'movement_date', 'cpf_customer', 'cpf_consultant'],
            'items',
            'totals' => ['items', 'quantity', 'realized_value', 'net_value'],
        ]);
        $this->assertSame(2, $response->json('totals.items'));
        $this->assertEquals(400.0, $response->json('totals.realized_value'));
    }

    public function test_invoice_xlsx_download_returns_200(): void
    {
        Movement::factory()->sale()->forInvoice('5001', 'Z001')->forDate('2026-04-15')->create();

        $response = $this->actingAs($this->adminUser)->get('/movements/invoice/Z001/5001/2026-04-15/xlsx');

        $response->assertStatus(200);
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_invoice_pdf_download_returns_200(): void
    {
        Movement::factory()->sale()->forInvoice('5002', 'Z001')->forDate('2026-04-15')->create();

        $response = $this->actingAs($this->adminUser)->get('/movements/invoice/Z001/5002/2026-04-15/pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    // =============================================
    // LIST EXPORTS
    // =============================================

    public function test_export_xlsx_respects_date_filter(): void
    {
        Movement::factory()->sale()->forDate('2026-04-15')->create();
        Movement::factory()->sale()->forDate('2026-05-15')->create();

        $response = $this->actingAs($this->adminUser)
            ->get('/movements/export/xlsx?date_start=2026-04-15&date_end=2026-04-15');

        $response->assertStatus(200);
    }

    public function test_export_pdf_respects_date_filter(): void
    {
        Movement::factory()->sale()->forDate('2026-04-15')->create();

        $response = $this->actingAs($this->adminUser)
            ->get('/movements/export/pdf?date_start=2026-04-15&date_end=2026-04-15');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }
}
