<?php

namespace Tests\Feature;

use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
