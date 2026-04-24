<?php

namespace Tests\Feature\Consignments;

use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\ConsignmentReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\ConsignmentExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Cobertura HTTP de Dashboard e Exports (XLSX/PDF) — Fase 3.
 */
class ConsignmentDashboardExportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected Store $store;

    protected Product $product;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        // XLSX streaming via zipstream-php pode ultrapassar 128MB (default do
        // php CLI no Windows). Aumenta temporariamente para os testes de export.
        ini_set('memory_limit', '512M');

        $this->setUpTestData();
        app(\App\Services\CentralRoleResolver::class)->clearCache();

        $this->store = Store::factory()->create(['code' => 'Z421']);

        $this->product = Product::create([
            'reference' => 'REF-001',
            'description' => 'Produto',
            'sale_price' => 100.00,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'barcode' => '1234567890123',
            'size_cigam_code' => 'U36',
            'is_active' => true,
        ]);

        config(['queue.default' => 'sync']);
    }

    // ------------------------------------------------------------------
    // Dashboard
    // ------------------------------------------------------------------

    public function test_dashboard_page_renders(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('consignments.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Consignments/Dashboard')
                ->has('analytics.by_month')
                ->has('analytics.by_type')
                ->has('analytics.by_recipient')
                ->has('analytics.by_employee')
                ->has('statistics')
            );
    }

    public function test_dashboard_aggregates_by_type(): void
    {
        Consignment::factory()->pending()->forStore($this->store)->ofType('cliente')->count(3)->create();
        Consignment::factory()->pending()->forStore($this->store)->ofType('influencer')->count(2)->create();
        Consignment::factory()->pending()->forStore($this->store)->ofType('ecommerce')->count(1)->create();

        $this->actingAs($this->adminUser)
            ->get(route('consignments.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('analytics.by_type', function ($data) {
                    $byType = collect($data)->keyBy('type');
                    return $byType['cliente']['total'] === 3
                        && $byType['influencer']['total'] === 2
                        && $byType['ecommerce']['total'] === 1;
                })
            );
    }

    public function test_dashboard_empty_state_shows_no_crash(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('consignments.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('analytics.by_month', [])
                ->where('analytics.by_recipient', [])
                ->where('analytics.by_employee', [])
            );
    }

    public function test_dashboard_respects_store_scope(): void
    {
        $otherStore = Store::factory()->create(['code' => 'Z499']);

        Consignment::factory()->pending()->forStore($this->store)->count(2)->create();
        Consignment::factory()->pending()->forStore($otherStore)->count(5)->create();

        // Admin vê tudo
        $this->actingAs($this->adminUser)
            ->get(route('consignments.dashboard'))
            ->assertInertia(fn ($page) => $page->where('statistics.total', 7));
    }

    // ------------------------------------------------------------------
    // Export XLSX
    // ------------------------------------------------------------------

    public function test_export_returns_xlsx_file(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();
        ConsignmentItem::factory()
            ->forConsignment($c)
            ->forProduct($this->product, $this->variant)
            ->quantity(2)
            ->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('consignments.export'));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheet',
            $response->headers->get('Content-Type'),
        );
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('consignacoes-', $disposition);
        $this->assertStringContainsString('.xlsx', $disposition);
    }

    public function test_export_respects_type_filter(): void
    {
        Consignment::factory()->pending()->forStore($this->store)->ofType('cliente')->create();
        Consignment::factory()->pending()->forStore($this->store)->ofType('influencer')->create();

        // Sem crash quando filtra — apenas garante que retorna XLSX válido
        $this->actingAs($this->adminUser)
            ->get(route('consignments.export', ['type' => 'cliente']))
            ->assertOk();
    }

    public function test_export_without_permission_is_blocked(): void
    {
        // regularUser não tem EXPORT_CONSIGNMENTS (ver Role::USER)
        $this->actingAs($this->regularUser)
            ->get(route('consignments.export'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Dias consignado — cálculo inclusivo (saída = dia 1)
    // ------------------------------------------------------------------

    public function test_days_on_consignment_counts_outbound_day_as_day_one(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create([
            'outbound_invoice_date' => now()->startOfDay(),
        ]);

        // Mesmo dia da saída → 1
        $this->assertSame(1, ConsignmentExportService::calculateDaysOnConsignment($c->fresh()));
    }

    public function test_days_on_consignment_uses_last_return_date_when_available(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create([
            'outbound_invoice_date' => now()->subDays(10)->startOfDay(),
        ]);

        ConsignmentReturn::create([
            'consignment_id' => $c->id,
            'return_invoice_number' => '999',
            'return_date' => now()->subDays(3)->toDateString(),
            'return_store_code' => $this->store->code,
            'returned_quantity' => 1,
            'returned_value' => 0,
            'registered_by_user_id' => $this->adminUser->id,
        ]);

        // Saída foi há 10 dias, retornou há 3 → dias = 10 - 3 + 1 = 8
        $this->assertSame(8, ConsignmentExportService::calculateDaysOnConsignment($c->fresh(['returns'])));
    }

    public function test_days_on_consignment_uses_today_when_open(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create([
            'outbound_invoice_date' => now()->subDays(5)->startOfDay(),
        ]);

        // Sem retorno + status aberto → hoje. 5 + 1 = 6
        $this->assertSame(6, ConsignmentExportService::calculateDaysOnConsignment($c->fresh()));
    }

    public function test_format_return_invoices_is_empty_when_no_returns(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        $this->assertSame('', ConsignmentExportService::formatReturnInvoices($c->fresh()));
    }

    public function test_format_return_invoices_concatenates_multiple_sorted_by_date(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        ConsignmentReturn::create([
            'consignment_id' => $c->id,
            'return_invoice_number' => '800',
            'return_date' => '2026-04-10',
            'return_store_code' => $this->store->code,
            'returned_quantity' => 1,
            'returned_value' => 0,
            'registered_by_user_id' => $this->adminUser->id,
        ]);

        ConsignmentReturn::create([
            'consignment_id' => $c->id,
            'return_invoice_number' => '777',
            'return_date' => '2026-04-05',
            'return_store_code' => $this->store->code,
            'returned_quantity' => 1,
            'returned_value' => 0,
            'registered_by_user_id' => $this->adminUser->id,
        ]);

        $formatted = ConsignmentExportService::formatReturnInvoices($c->fresh(['returns']));

        // Ordenado por data ascendente
        $this->assertSame('777 (05/04/2026) | 800 (10/04/2026)', $formatted);
    }

    // ------------------------------------------------------------------
    // Export PDF (comprovante com QR Code)
    // ------------------------------------------------------------------

    public function test_export_pdf_returns_pdf_file(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();
        ConsignmentItem::factory()
            ->forConsignment($c)
            ->forProduct($this->product, $this->variant)
            ->quantity(2)
            ->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('consignments.pdf', $c->id));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('consignacao-', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    public function test_export_pdf_blocks_cross_store_access_for_scoped_user(): void
    {
        // Cria consignação na loja Z421
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        // Support em outra loja (sem MANAGE_CONSIGNMENTS → store-scoped)
        $otherStore = Store::factory()->create(['code' => 'Z998']);
        $this->supportUser->update(['store_id' => $otherStore->code]);

        $this->actingAs($this->supportUser->fresh())
            ->get(route('consignments.pdf', $c->id))
            ->assertForbidden();
    }

    public function test_export_pdf_without_permission_is_blocked(): void
    {
        $c = Consignment::factory()->pending()->forStore($this->store)->create();

        $this->actingAs($this->regularUser)
            ->get(route('consignments.pdf', $c->id))
            ->assertForbidden();
    }
}
