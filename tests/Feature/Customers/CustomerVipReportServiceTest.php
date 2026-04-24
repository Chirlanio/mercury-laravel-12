<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Services\CustomerVipReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CustomerVipReportServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private CustomerVipReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->service = app(CustomerVipReportService::class);

        if (! Schema::hasTable('movements')) {
            Schema::create('movements', function ($table) {
                $table->id();
                $table->date('movement_date');
                $table->string('cpf_customer', 14)->nullable();
                $table->integer('movement_code');
                $table->char('entry_exit', 1);
                $table->decimal('net_value', 12, 2)->default(0);
                $table->string('invoice_number', 30)->nullable();
                $table->string('store_code', 10)->nullable();
                $table->timestamps();
            });
        }
    }

    private function makeCustomer(string $cpf): Customer
    {
        return Customer::create([
            'cigam_code' => '10001-'.substr($cpf, -4),
            'name' => 'CLIENTE',
            'cpf' => $cpf,
            'is_active' => true,
            'synced_at' => now(),
        ]);
    }

    private function makeSale(string $cpf, string $date, float $value, ?string $invoice = null): void
    {
        DB::table('movements')->insert([
            'movement_date' => $date,
            'cpf_customer' => $cpf,
            'movement_code' => 2,
            'entry_exit' => 'S',
            'net_value' => $value,
            'invoice_number' => $invoice ?: (string) random_int(1000, 99999),
            'store_code' => 'Z441',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeReturn(string $cpf, string $date, float $value): void
    {
        DB::table('movements')->insert([
            'movement_date' => $date,
            'cpf_customer' => $cpf,
            'movement_code' => 6,
            'entry_exit' => 'E',
            'net_value' => $value,
            'invoice_number' => (string) random_int(1000, 99999),
            'store_code' => 'Z441',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --------------------------------------------------------------

    public function test_full_year_compares_both_years_completely(): void
    {
        $customer = $this->makeCustomer('11111111111');

        $this->makeSale('11111111111', '2024-03-10', 3000);
        $this->makeSale('11111111111', '2024-11-20', 2000);

        $this->makeSale('11111111111', '2025-03-10', 4000);
        $this->makeSale('11111111111', '2025-11-20', 3000);
        $this->makeReturn('11111111111', '2025-12-01', 500);

        $report = $this->service->yearOverYear($customer, 2025, 'full_year');

        $this->assertEqualsWithDelta(6500.0, $report['current']['total'], 0.01);
        $this->assertEqualsWithDelta(5000.0, $report['previous']['total'], 0.01);
        $this->assertEqualsWithDelta(1500.0, $report['delta']['absolute'], 0.01);
        $this->assertEqualsWithDelta(30.0, $report['delta']['pct'], 0.01);
    }

    public function test_monthly_breakdown_has_twelve_buckets_with_values_in_correct_months(): void
    {
        $customer = $this->makeCustomer('22222222222');
        $this->makeSale('22222222222', '2025-01-15', 1000);
        $this->makeSale('22222222222', '2025-01-28', 500);
        $this->makeSale('22222222222', '2025-06-10', 2000);

        $report = $this->service->yearOverYear($customer, 2025, 'full_year');

        $monthly = $report['current']['monthly'];
        $this->assertCount(12, $monthly);
        $this->assertEqualsWithDelta(1500.0, $monthly[1], 0.01);
        $this->assertEqualsWithDelta(0.0, $monthly[2], 0.01);
        $this->assertEqualsWithDelta(2000.0, $monthly[6], 0.01);
    }

    public function test_delta_pct_is_null_when_previous_is_zero(): void
    {
        $customer = $this->makeCustomer('33333333333');
        $this->makeSale('33333333333', '2025-05-10', 1000);

        $report = $this->service->yearOverYear($customer, 2025, 'full_year');

        $this->assertEqualsWithDelta(1000.0, $report['current']['total'], 0.01);
        $this->assertEqualsWithDelta(0.0, $report['previous']['total'], 0.01);
        $this->assertNull($report['delta']['pct']);
    }

    public function test_customer_without_cpf_returns_empty_payload(): void
    {
        $customer = Customer::create([
            'cigam_code' => '99999-999',
            'name' => 'SEM CPF',
            'cpf' => null,
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $report = $this->service->yearOverYear($customer, 2025, 'full_year');

        $this->assertEqualsWithDelta(0.0, $report['current']['total'], 0.01);
        $this->assertEqualsWithDelta(0.0, $report['previous']['total'], 0.01);
    }

    public function test_revenue_in_range_returns_raw_total(): void
    {
        $customer = $this->makeCustomer('44444444444');
        $this->makeSale('44444444444', '2025-06-10', 3000);
        $this->makeSale('44444444444', '2025-08-15', 2000);
        $this->makeReturn('44444444444', '2025-07-01', 500);

        // Janela que inclui só até julho
        $total = $this->service->revenueInRange($customer, '2025-01-01', '2025-07-31');
        $this->assertEqualsWithDelta(2500.0, $total, 0.01); // 3000 - 500
    }
}
