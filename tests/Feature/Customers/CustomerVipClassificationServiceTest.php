<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\CustomerVipTier;
use App\Models\CustomerVipTierConfig;
use App\Services\CustomerVipClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CustomerVipClassificationServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private CustomerVipClassificationService $service;

    private int $year = 2025;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();
        $this->service = app(CustomerVipClassificationService::class);

        // Minimal movements schema for SQLite — real tenant migrations não rodam
        // em in-memory SQLite padrão, então criamos as colunas que o service usa.
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

    private function makeCustomer(string $cpf, string $name = 'CLIENTE'): Customer
    {
        return Customer::create([
            'cigam_code' => '10001-'.substr($cpf, -4),
            'name' => $name,
            'cpf' => $cpf,
            'is_active' => true,
            'synced_at' => now(),
        ]);
    }

    private function makeMovement(array $overrides): void
    {
        DB::table('movements')->insert(array_merge([
            'movement_date' => sprintf('%d-03-15', $this->year),
            'movement_code' => 2,
            'entry_exit' => 'S',
            'net_value' => 1000.00,
            'invoice_number' => (string) random_int(1000, 99999),
            'store_code' => 'Z441',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function setThresholds(float $black = 10000, float $gold = 5000): void
    {
        CustomerVipTierConfig::create(['year' => $this->year, 'tier' => 'black', 'min_revenue' => $black]);
        CustomerVipTierConfig::create(['year' => $this->year, 'tier' => 'gold', 'min_revenue' => $gold]);
    }

    // --------------------------------------------------------------
    // Cálculo de faturamento
    // --------------------------------------------------------------

    public function test_revenue_sums_code_2_and_subtracts_code_6_entry(): void
    {
        $customer = $this->makeCustomer('11111111111', 'JOAO');
        $this->setThresholds(black: 10000, gold: 5000);

        // 3 vendas de R$ 3000 + 1 devolução de R$ 500 = 8500 (Gold)
        $this->makeMovement(['cpf_customer' => '11111111111', 'movement_code' => 2, 'entry_exit' => 'S', 'net_value' => 3000, 'invoice_number' => '1001']);
        $this->makeMovement(['cpf_customer' => '11111111111', 'movement_code' => 2, 'entry_exit' => 'S', 'net_value' => 3000, 'invoice_number' => '1002']);
        $this->makeMovement(['cpf_customer' => '11111111111', 'movement_code' => 2, 'entry_exit' => 'S', 'net_value' => 3000, 'invoice_number' => '1003']);
        $this->makeMovement(['cpf_customer' => '11111111111', 'movement_code' => 6, 'entry_exit' => 'E', 'net_value' => 500, 'invoice_number' => '1004']);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['suggested_gold']);
        $this->assertSame(0, $summary['suggested_black']);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertNotNull($tier);
        $this->assertEqualsWithDelta(8500.0, (float) $tier->total_revenue, 0.01);
        $this->assertEquals('gold', $tier->suggested_tier);
        $this->assertEquals('gold', $tier->final_tier);
        $this->assertEquals(4, $tier->total_orders);
    }

    public function test_revenue_ignores_code_6_exit_and_other_codes(): void
    {
        $customer = $this->makeCustomer('22222222222');
        $this->setThresholds(10000, 5000);

        $this->makeMovement(['cpf_customer' => '22222222222', 'movement_code' => 2, 'entry_exit' => 'S', 'net_value' => 6000]);
        // code 6 'S' (saída) — não é devolução, deve ser ignorado
        $this->makeMovement(['cpf_customer' => '22222222222', 'movement_code' => 6, 'entry_exit' => 'S', 'net_value' => 500]);
        // code 1 (compra) — deve ser ignorado
        $this->makeMovement(['cpf_customer' => '22222222222', 'movement_code' => 1, 'entry_exit' => 'E', 'net_value' => 100000]);

        $this->service->generateSuggestions($this->year);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEqualsWithDelta(6000.0, (float) $tier->total_revenue, 0.01);
        $this->assertEquals('gold', $tier->suggested_tier);
    }

    public function test_revenue_ignores_other_years(): void
    {
        $customer = $this->makeCustomer('33333333333');
        $this->setThresholds(10000, 5000);

        $this->makeMovement(['cpf_customer' => '33333333333', 'movement_date' => $this->year.'-05-01', 'net_value' => 6000]);
        $this->makeMovement(['cpf_customer' => '33333333333', 'movement_date' => ($this->year - 1).'-05-01', 'net_value' => 100000]);
        $this->makeMovement(['cpf_customer' => '33333333333', 'movement_date' => ($this->year + 1).'-05-01', 'net_value' => 100000]);

        $this->service->generateSuggestions($this->year);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEqualsWithDelta(6000.0, (float) $tier->total_revenue, 0.01);
    }

    // --------------------------------------------------------------
    // Thresholds e tiers
    // --------------------------------------------------------------

    public function test_assigns_black_when_revenue_above_black_threshold(): void
    {
        $customer = $this->makeCustomer('44444444444');
        $this->setThresholds(black: 10000, gold: 5000);

        $this->makeMovement(['cpf_customer' => '44444444444', 'net_value' => 15000]);

        $this->service->generateSuggestions($this->year);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEquals('black', $tier->suggested_tier);
        $this->assertEquals('black', $tier->final_tier);
    }

    public function test_no_tier_suggested_when_below_gold_threshold(): void
    {
        $customer = $this->makeCustomer('55555555555');
        $this->setThresholds(10000, 5000);

        $this->makeMovement(['cpf_customer' => '55555555555', 'net_value' => 3000]);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertSame(1, $summary['below_threshold']);
        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertNull($tier->suggested_tier);
        $this->assertNull($tier->final_tier);
    }

    public function test_preserves_manual_curation(): void
    {
        $customer = $this->makeCustomer('66666666666');
        $this->setThresholds(10000, 5000);

        // Curadoria manual prévia — promove pra Black
        $this->service->curate($customer, $this->year, 'black', 'Decidido pela diretoria', $this->adminUser);

        // Novos movements que indicariam apenas Gold
        $this->makeMovement(['cpf_customer' => '66666666666', 'net_value' => 7000]);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertSame(1, $summary['preserved_curated']);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertEquals('black', $tier->final_tier, 'final_tier não deve ser rebaixado');
        $this->assertEquals('gold', $tier->suggested_tier, 'suggested_tier deve refletir o cálculo atual');
        $this->assertEqualsWithDelta(7000.0, (float) $tier->total_revenue, 0.01);
    }

    // --------------------------------------------------------------
    // Curate / Remove
    // --------------------------------------------------------------

    public function test_curate_marks_source_manual(): void
    {
        $customer = $this->makeCustomer('77777777777');

        $record = $this->service->curate($customer, $this->year, 'gold', null, $this->adminUser);

        $this->assertEquals(CustomerVipTier::SOURCE_MANUAL, $record->source);
        $this->assertEquals($this->adminUser->id, $record->curated_by_user_id);
        $this->assertNotNull($record->curated_at);
    }

    public function test_remove_nullifies_final_tier_but_keeps_history(): void
    {
        $customer = $this->makeCustomer('88888888888');
        $this->setThresholds(10000, 5000);
        $this->makeMovement(['cpf_customer' => '88888888888', 'net_value' => 12000]);
        $this->service->generateSuggestions($this->year);

        $this->service->remove($customer, $this->year, $this->adminUser);

        $tier = CustomerVipTier::where('customer_id', $customer->id)->first();
        $this->assertNotNull($tier, 'record preservado');
        $this->assertNull($tier->final_tier, 'final_tier zerado');
        $this->assertEquals('black', $tier->suggested_tier, 'histórico da sugestão preservado');
        $this->assertEqualsWithDelta(12000.0, (float) $tier->total_revenue, 0.01);
    }

    public function test_curate_rejects_invalid_tier(): void
    {
        $customer = $this->makeCustomer('99999999999');
        $this->expectException(\InvalidArgumentException::class);
        $this->service->curate($customer, $this->year, 'platinum', null, $this->adminUser);
    }

    // --------------------------------------------------------------
    // Matching por CPF
    // --------------------------------------------------------------

    public function test_ignores_cpfs_without_matching_customer(): void
    {
        $this->setThresholds(10000, 5000);

        // Movement com CPF que não existe em customers
        $this->makeMovement(['cpf_customer' => '00000000000', 'net_value' => 50000]);

        $summary = $this->service->generateSuggestions($this->year);

        $this->assertSame(0, $summary['processed']);
        $this->assertSame(0, CustomerVipTier::count());
    }
}
