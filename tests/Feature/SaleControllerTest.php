<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class SaleControllerTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->testStoreId = $this->createTestStore('Z999');
        $this->testEmployeeId = $this->createTestEmployee();
    }

    // ==================== INDEX ====================

    public function test_sales_index_requires_authentication(): void
    {
        $response = $this->get('/sales');
        $response->assertRedirect('/login');
    }

    public function test_sales_index_requires_view_sales_permission(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/sales');
        $response->assertStatus(403);
    }

    public function test_sales_index_displays_for_authorized_user(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/sales');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Sales/Index'));
    }

    public function test_sales_index_returns_grouped_data(): void
    {
        $this->createTestSale(['date_sales' => now()->format('Y-m-d')]);

        $response = $this->actingAs($this->adminUser)->get('/sales?month=' . now()->month . '&year=' . now()->year);
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Sales/Index')
            ->has('salesByStore')
            ->has('grandTotals')
            ->has('stores')
            ->has('filters')
        );

        $props = $response->original->getData()['page']['props'];
        $this->assertCount(1, $props['salesByStore']);
        $this->assertEquals($this->testStoreId, $props['salesByStore'][0]['store_id']);
        $this->assertCount(1, $props['salesByStore'][0]['employees']);
    }

    public function test_sales_index_filters_by_store(): void
    {
        $this->createTestSale(['date_sales' => now()->format('Y-m-d')]);

        $response = $this->actingAs($this->adminUser)->get('/sales?store_id=' . $this->testStoreId);
        $response->assertStatus(200);
    }

    public function test_sales_index_filters_by_month_year(): void
    {
        $this->createTestSale(['date_sales' => '2026-01-15']);

        $response = $this->actingAs($this->adminUser)->get('/sales?month=1&year=2026');
        $response->assertStatus(200);
    }

    public function test_sales_index_search_filters_by_employee_name(): void
    {
        $emp1 = $this->createTestEmployee(['cpf' => '11111111111', 'name' => 'MARIA SILVA', 'short_name' => 'MARIA']);
        $emp2 = $this->createTestEmployee(['cpf' => '22222222222', 'name' => 'JOANA SOUZA', 'short_name' => 'JOANA']);

        $this->createTestSale(['date_sales' => now()->format('Y-m-d'), 'employee_id' => $emp1]);
        $this->createTestSale(['date_sales' => now()->format('Y-m-d'), 'employee_id' => $emp2]);

        $response = $this->actingAs($this->adminUser)->get('/sales?search=MARIA&month=' . now()->month . '&year=' . now()->year);
        $response->assertStatus(200);

        $props = $response->original->getData()['page']['props'];
        $salesByStore = $props['salesByStore'];

        // Only MARIA should appear
        $allEmployees = collect($salesByStore)->pluck('employees')->flatten(1);
        $this->assertTrue($allEmployees->contains('employee_id', $emp1));
        $this->assertFalse($allEmployees->contains('employee_id', $emp2));
    }

    // ==================== STORE ====================

    public function test_sales_can_be_created(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/sales', [
            'store_id' => $this->testStoreId,
            'employee_id' => $this->testEmployeeId,
            'date_sales' => '2026-01-20',
            'total_sales' => 2500.50,
            'qtde_total' => 15,
        ]);

        $response->assertRedirect(route('sales.index'));
        $this->assertDatabaseHas('sales', [
            'store_id' => $this->testStoreId,
            'employee_id' => $this->testEmployeeId,
            'total_sales' => 2500.50,
            'qtde_total' => 15,
        ]);
    }

    public function test_sales_creation_requires_store_id(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/sales', [
            'employee_id' => $this->testEmployeeId,
            'date_sales' => '2026-01-20',
            'total_sales' => 2500.50,
            'qtde_total' => 15,
        ]);

        $response->assertSessionHasErrors('store_id');
    }

    public function test_sales_creation_requires_employee_id(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/sales', [
            'store_id' => $this->testStoreId,
            'date_sales' => '2026-01-20',
            'total_sales' => 2500.50,
            'qtde_total' => 15,
        ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_sales_creation_prevents_duplicate(): void
    {
        $this->createTestSale([
            'store_id' => $this->testStoreId,
            'employee_id' => $this->testEmployeeId,
            'date_sales' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->adminUser)->post('/sales', [
            'store_id' => $this->testStoreId,
            'employee_id' => $this->testEmployeeId,
            'date_sales' => '2026-01-15',
            'total_sales' => 3000.00,
            'qtde_total' => 20,
        ]);

        $response->assertSessionHasErrors('date_sales');
    }

    public function test_sales_creation_prevents_future_date(): void
    {
        $futureDate = now()->addDays(5)->format('Y-m-d');

        $response = $this->actingAs($this->adminUser)->post('/sales', [
            'store_id' => $this->testStoreId,
            'employee_id' => $this->testEmployeeId,
            'date_sales' => $futureDate,
            'total_sales' => 2500.50,
            'qtde_total' => 15,
        ]);

        $response->assertSessionHasErrors('date_sales');
    }

    // ==================== SHOW ====================

    public function test_sales_show_returns_json(): void
    {
        $saleId = $this->createTestSale();

        $response = $this->actingAs($this->adminUser)->get("/sales/{$saleId}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'sale' => ['id', 'date_sales', 'store_name', 'employee_name', 'total_sales', 'qtde_total', 'source'],
        ]);
    }

    // ==================== UPDATE ====================

    public function test_sales_can_be_updated(): void
    {
        $saleId = $this->createTestSale();

        $response = $this->actingAs($this->adminUser)->put("/sales/{$saleId}", [
            'store_id' => $this->testStoreId,
            'employee_id' => $this->testEmployeeId,
            'date_sales' => '2026-01-16',
            'total_sales' => 3500.00,
            'qtde_total' => 25,
        ]);

        $response->assertRedirect(route('sales.index'));
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'total_sales' => 3500.00,
            'qtde_total' => 25,
        ]);
    }

    // ==================== DELETE ====================

    public function test_sales_can_be_deleted(): void
    {
        $saleId = $this->createTestSale();

        $response = $this->actingAs($this->adminUser)->delete("/sales/{$saleId}");

        $response->assertRedirect(route('sales.index'));
        $this->assertDatabaseMissing('sales', ['id' => $saleId]);
    }

    // ==================== STATISTICS ====================

    public function test_sales_statistics_returns_correct_data(): void
    {
        $this->createTestSale([
            'date_sales' => now()->format('Y-m-d'),
            'total_sales' => 1000.00,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/sales/statistics?month=' . now()->month . '&year=' . now()->year);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'current_month_total',
            'last_month_total',
            'variation',
            'same_month_last_year',
            'yoy_variation',
            'active_stores',
            'active_consultants',
            'total_records',
            'avg_per_store',
            'avg_per_consultant',
        ]);
    }

    public function test_sales_statistics_filters_by_store_for_non_admin(): void
    {
        $this->createTestSale(['date_sales' => now()->format('Y-m-d')]);

        $response = $this->actingAs($this->supportUser)->get('/sales/statistics?month=' . now()->month . '&year=' . now()->year);
        $response->assertStatus(200);
    }

    // ==================== EMPLOYEE DAILY SALES ====================

    public function test_employee_daily_sales_returns_data(): void
    {
        $this->createTestSale([
            'date_sales' => '2026-01-15',
            'total_sales' => 1000.00,
            'qtde_total' => 5,
        ]);
        $this->createTestSale([
            'date_sales' => '2026-01-16',
            'total_sales' => 2000.00,
            'qtde_total' => 10,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/sales/employee-daily?' . http_build_query([
            'employee_id' => $this->testEmployeeId,
            'store_id' => $this->testStoreId,
            'month' => 1,
            'year' => 2026,
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'employee' => ['id', 'name', 'short_name'],
            'store' => ['id', 'name'],
            'daily_sales',
            'totals' => ['store_total', 'store_qtde', 'ecommerce_total', 'ecommerce_qtde', 'total', 'total_qtde'],
        ]);

        $data = $response->json();
        $this->assertCount(2, $data['daily_sales']);
        $this->assertEquals(3000.00, $data['totals']['total']);
        $this->assertEquals(15, $data['totals']['total_qtde']);
    }

    public function test_employee_daily_sales_includes_ecommerce(): void
    {
        $ecommerceStoreId = $this->createTestStore(Store::ECOMMERCE_CODE, [
            'name' => 'E-COMMERCE',
            'network_id' => 6,
        ]);

        // Physical store sale
        $this->createTestSale([
            'store_id' => $this->testStoreId,
            'employee_id' => $this->testEmployeeId,
            'date_sales' => '2026-01-15',
            'total_sales' => 1000.00,
            'qtde_total' => 5,
        ]);

        // E-commerce sale by same employee
        $this->createTestSale([
            'store_id' => $ecommerceStoreId,
            'employee_id' => $this->testEmployeeId,
            'date_sales' => '2026-01-16',
            'total_sales' => 500.00,
            'qtde_total' => 3,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/sales/employee-daily?' . http_build_query([
            'employee_id' => $this->testEmployeeId,
            'store_id' => $this->testStoreId,
            'month' => 1,
            'year' => 2026,
        ]));

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(2, $data['daily_sales']);
        $this->assertEquals(1000.00, $data['totals']['store_total']);
        $this->assertEquals(5, $data['totals']['store_qtde']);
        $this->assertEquals(500.00, $data['totals']['ecommerce_total']);
        $this->assertEquals(3, $data['totals']['ecommerce_qtde']);
        $this->assertEquals(1500.00, $data['totals']['total']);

        // Verify is_ecommerce flag
        $ecommerceSales = collect($data['daily_sales'])->where('is_ecommerce', true);
        $this->assertCount(1, $ecommerceSales);
    }

    public function test_employee_daily_sales_requires_view_sales_permission(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/sales/employee-daily?' . http_build_query([
            'employee_id' => $this->testEmployeeId,
            'store_id' => $this->testStoreId,
            'month' => 1,
            'year' => 2026,
        ]));

        $response->assertStatus(403);
    }

    public function test_employee_daily_sales_validates_required_params(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/sales/employee-daily');
        $response->assertStatus(302); // Validation redirect
    }

    // ==================== BULK DELETE ====================

    public function test_bulk_delete_preview_returns_summary(): void
    {
        $this->createTestSale(['date_sales' => '2026-01-15']);
        $this->createTestSale([
            'date_sales' => '2026-01-16',
            'employee_id' => $this->createTestEmployee(['cpf' => '99988877766', 'name' => 'SECOND EMPLOYEE']),
        ]);

        $response = $this->actingAs($this->adminUser)->post('/sales/bulk-delete/preview', [
            'mode' => 'month',
            'month' => 1,
            'year' => 2026,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['total_records', 'total_value', 'affected_stores', 'affected_employees']);
        $this->assertEquals(2, $response->json('total_records'));
    }

    public function test_bulk_delete_removes_records(): void
    {
        $this->createTestSale(['date_sales' => '2026-01-15']);
        $this->createTestSale([
            'date_sales' => '2026-01-16',
            'employee_id' => $this->createTestEmployee(['cpf' => '99988877766', 'name' => 'SECOND EMPLOYEE']),
        ]);

        $response = $this->actingAs($this->adminUser)->post('/sales/bulk-delete', [
            'mode' => 'month',
            'month' => 1,
            'year' => 2026,
        ]);

        $response->assertRedirect(route('sales.index'));
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_bulk_delete_requires_delete_permission(): void
    {
        $response = $this->actingAs($this->supportUser)->post('/sales/bulk-delete', [
            'mode' => 'month',
            'month' => 1,
            'year' => 2026,
        ]);

        $response->assertStatus(403);
    }

    // ==================== PERMISSIONS ====================

    public function test_support_can_view_but_not_create(): void
    {
        $viewResponse = $this->actingAs($this->supportUser)->get('/sales');
        $viewResponse->assertStatus(200);

        $createResponse = $this->actingAs($this->supportUser)->post('/sales', [
            'store_id' => $this->testStoreId,
            'employee_id' => $this->testEmployeeId,
            'date_sales' => '2026-01-20',
            'total_sales' => 2500.50,
            'qtde_total' => 15,
        ]);
        $createResponse->assertStatus(403);
    }

    public function test_user_without_permission_cannot_access(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/sales');
        $response->assertStatus(403);
    }

    // ==================== ECOMMERCE FILTER ====================

    protected function createEcommerceScenario(): array
    {
        $physicalStoreId = $this->createTestStore(
            'Z421',
            ['name' => 'LOJA FISICA', 'network_id' => 1]
        );
        $ecommerceStoreId = $this->createTestStore(
            Store::ECOMMERCE_CODE,
            ['name' => 'E-COMMERCE', 'network_id' => 6]
        );
        $otherStoreId = $this->createTestStore(
            'Z422',
            ['name' => 'OUTRA LOJA', 'network_id' => 1]
        );

        // Employee with contract at physical store
        $employeePhysical = $this->createTestEmployee([
            'cpf' => '11111111111',
            'name' => 'EMP PHYSICAL',
            'short_name' => 'PHYSICAL',
            'store_id' => Store::ECOMMERCE_CODE,
        ]);
        DB::table('employment_contracts')->insert([
            'employee_id' => $employeePhysical,
            'position_id' => 1,
            'movement_type_id' => 1,
            'start_date' => now()->subYear(),
            'end_date' => null,
            'store_id' => 'Z421',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Employee with contract at another store
        $employeeOther = $this->createTestEmployee([
            'cpf' => '22222222222',
            'name' => 'EMP OTHER',
            'short_name' => 'OTHER',
            'store_id' => Store::ECOMMERCE_CODE,
        ]);
        DB::table('employment_contracts')->insert([
            'employee_id' => $employeeOther,
            'position_id' => 1,
            'movement_type_id' => 1,
            'start_date' => now()->subYear(),
            'end_date' => null,
            'store_id' => 'Z422',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact(
            'physicalStoreId',
            'ecommerceStoreId',
            'otherStoreId',
            'employeePhysical',
            'employeeOther'
        );
    }

    public function test_ecommerce_physical_store_sale_appears_when_filtering_by_that_store(): void
    {
        $s = $this->createEcommerceScenario();

        $this->createTestSale([
            'store_id' => $s['physicalStoreId'],
            'employee_id' => $s['employeePhysical'],
            'date_sales' => now()->format('Y-m-d'),
            'total_sales' => 1000.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/sales?store_id=' . $s['physicalStoreId'] . '&month=' . now()->month . '&year=' . now()->year);
        $response->assertStatus(200);

        $salesByStore = $response->original->getData()['page']['props']['salesByStore'];
        $allEmployees = collect($salesByStore)->pluck('employees')->flatten(1);
        $this->assertCount(1, $allEmployees);
        $this->assertEquals(1000.00, $allEmployees->first()['total_sales']);
    }

    public function test_ecommerce_sale_by_contract_employee_appears_when_filtering_physical_store(): void
    {
        $s = $this->createEcommerceScenario();

        // Sale in ecommerce by employee with contract at physical store
        $this->createTestSale([
            'store_id' => $s['ecommerceStoreId'],
            'employee_id' => $s['employeePhysical'],
            'date_sales' => now()->format('Y-m-d'),
            'total_sales' => 2000.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/sales?store_id=' . $s['physicalStoreId'] . '&month=' . now()->month . '&year=' . now()->year);
        $response->assertStatus(200);

        $salesByStore = $response->original->getData()['page']['props']['salesByStore'];
        // All sales grouped under the filtered physical store
        $this->assertCount(1, $salesByStore);
        $this->assertEquals($s['physicalStoreId'], $salesByStore[0]['store_id']);
        $allEmployees = collect($salesByStore)->pluck('employees')->flatten(1);
        $this->assertCount(1, $allEmployees);
        $this->assertEquals(2000.00, $allEmployees->first()['total_sales']);
    }

    public function test_store_filter_merges_physical_and_ecommerce_totals(): void
    {
        $s = $this->createEcommerceScenario();

        // Physical store sale
        $this->createTestSale([
            'store_id' => $s['physicalStoreId'],
            'employee_id' => $s['employeePhysical'],
            'date_sales' => now()->format('Y-m-d'),
            'total_sales' => 1000.00,
            'qtde_total' => 5,
        ]);

        // E-commerce sale by same employee
        $this->createTestSale([
            'store_id' => $s['ecommerceStoreId'],
            'employee_id' => $s['employeePhysical'],
            'date_sales' => now()->subDay()->format('Y-m-d'),
            'total_sales' => 500.00,
            'qtde_total' => 3,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/sales?store_id=' . $s['physicalStoreId'] . '&month=' . now()->month . '&year=' . now()->year);
        $response->assertStatus(200);

        $salesByStore = $response->original->getData()['page']['props']['salesByStore'];

        // Single store group with merged totals
        $this->assertCount(1, $salesByStore);
        $this->assertEquals($s['physicalStoreId'], $salesByStore[0]['store_id']);
        $this->assertEquals(1500.00, $salesByStore[0]['total_sales']);
        $this->assertEquals(8, $salesByStore[0]['qtde_total']);

        // Consultant total includes physical + e-commerce
        $emp = $salesByStore[0]['employees'][0];
        $this->assertEquals($s['employeePhysical'], $emp['employee_id']);
        $this->assertEquals(1500.00, $emp['total_sales']);
        $this->assertEquals(8, $emp['qtde_total']);
    }

    public function test_all_stores_view_remaps_ecommerce_to_physical_store(): void
    {
        $s = $this->createEcommerceScenario();

        // Physical store sale
        $this->createTestSale([
            'store_id' => $s['physicalStoreId'],
            'employee_id' => $s['employeePhysical'],
            'date_sales' => now()->format('Y-m-d'),
            'total_sales' => 1000.00,
            'qtde_total' => 5,
        ]);

        // E-commerce sale by employee contracted to physical store
        $this->createTestSale([
            'store_id' => $s['ecommerceStoreId'],
            'employee_id' => $s['employeePhysical'],
            'date_sales' => now()->subDay()->format('Y-m-d'),
            'total_sales' => 500.00,
            'qtde_total' => 3,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/sales?month=' . now()->month . '&year=' . now()->year);
        $response->assertStatus(200);

        $salesByStore = $response->original->getData()['page']['props']['salesByStore'];

        // E-commerce sale should be remapped to the physical store
        $physicalStoreGroup = collect($salesByStore)->firstWhere('store_id', $s['physicalStoreId']);
        $this->assertNotNull($physicalStoreGroup);
        $this->assertEquals(1500.00, $physicalStoreGroup['total_sales']);
        $this->assertEquals(8, $physicalStoreGroup['qtde_total']);

        // Consultant total includes both
        $emp = collect($physicalStoreGroup['employees'])->firstWhere('employee_id', $s['employeePhysical']);
        $this->assertNotNull($emp);
        $this->assertEquals(1500.00, $emp['total_sales']);
        $this->assertEquals(8, $emp['qtde_total']);

        // No separate e-commerce store group for this employee
        $ecommerceStoreGroup = collect($salesByStore)->firstWhere('store_id', $s['ecommerceStoreId']);
        $this->assertNull($ecommerceStoreGroup);
    }

    public function test_ecommerce_sale_by_other_store_employee_does_not_appear(): void
    {
        $s = $this->createEcommerceScenario();

        // Sale in ecommerce by employee with contract at OTHER store
        $this->createTestSale([
            'store_id' => $s['ecommerceStoreId'],
            'employee_id' => $s['employeeOther'],
            'date_sales' => now()->format('Y-m-d'),
            'total_sales' => 3000.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/sales?store_id=' . $s['physicalStoreId'] . '&month=' . now()->month . '&year=' . now()->year);
        $response->assertStatus(200);

        $salesByStore = $response->original->getData()['page']['props']['salesByStore'];
        $this->assertCount(0, $salesByStore);
    }

    public function test_ecommerce_filter_shows_only_ecommerce_sales(): void
    {
        $s = $this->createEcommerceScenario();

        // Sale in ecommerce
        $this->createTestSale([
            'store_id' => $s['ecommerceStoreId'],
            'employee_id' => $s['employeePhysical'],
            'date_sales' => now()->format('Y-m-d'),
            'total_sales' => 2000.00,
        ]);

        // Sale in physical store
        $this->createTestSale([
            'store_id' => $s['physicalStoreId'],
            'employee_id' => $s['employeePhysical'],
            'date_sales' => now()->subDay()->format('Y-m-d'),
            'total_sales' => 1000.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/sales?store_id=' . $s['ecommerceStoreId'] . '&month=' . now()->month . '&year=' . now()->year);
        $response->assertStatus(200);

        $salesByStore = $response->original->getData()['page']['props']['salesByStore'];
        $allEmployees = collect($salesByStore)->pluck('employees')->flatten(1);
        $this->assertCount(1, $allEmployees);
        $this->assertEquals(2000.00, $allEmployees->first()['total_sales']);
    }

    public function test_statistics_include_ecommerce_sales_for_physical_store(): void
    {
        $s = $this->createEcommerceScenario();

        // Physical store sale
        $this->createTestSale([
            'store_id' => $s['physicalStoreId'],
            'employee_id' => $s['employeePhysical'],
            'date_sales' => now()->format('Y-m-d'),
            'total_sales' => 1000.00,
        ]);

        // Ecommerce sale by employee contracted to physical store
        $this->createTestSale([
            'store_id' => $s['ecommerceStoreId'],
            'employee_id' => $s['employeePhysical'],
            'date_sales' => now()->subDay()->format('Y-m-d'),
            'total_sales' => 500.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/sales/statistics?store_id=' . $s['physicalStoreId'] . '&month=' . now()->month . '&year=' . now()->year);
        $response->assertStatus(200);
        $this->assertEquals(1500.00, $response->json('current_month_total'));
    }
}
