<?php

namespace Tests\Unit;

use App\Models\Movement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_scope_filters_movement_code_2(): void
    {
        Movement::factory()->sale()->create(['realized_value' => 100]);
        Movement::factory()->sale()->create(['realized_value' => 200]);
        Movement::factory()->returnEntry()->create(['realized_value' => 50]);

        $this->assertSame(2, Movement::sales()->count());
    }

    public function test_returns_scope_filters_code_6_entry_e(): void
    {
        Movement::factory()->returnEntry()->create();
        Movement::factory()->returnEntry()->create();
        Movement::factory()->sale()->create();
        Movement::factory()->create(['movement_code' => 6, 'entry_exit' => 'S']);

        $this->assertSame(2, Movement::returns()->count());
    }

    public function test_sales_and_returns_scope_includes_both(): void
    {
        Movement::factory()->sale()->create();
        Movement::factory()->returnEntry()->create();
        Movement::factory()->create(['movement_code' => 17]);

        $this->assertSame(2, Movement::salesAndReturns()->count());
    }

    public function test_for_date_scope(): void
    {
        Movement::factory()->forDate('2026-04-01')->create();
        Movement::factory()->forDate('2026-04-02')->create();
        Movement::factory()->forDate('2026-04-02')->create();

        $this->assertSame(2, Movement::forDate('2026-04-02')->count());
    }

    public function test_for_date_range_scope(): void
    {
        Movement::factory()->forDate('2026-04-01')->create();
        Movement::factory()->forDate('2026-04-05')->create();
        Movement::factory()->forDate('2026-04-10')->create();

        $this->assertSame(2, Movement::forDateRange('2026-04-01', '2026-04-05')->count());
    }

    public function test_for_store_scope(): void
    {
        Movement::factory()->forStore('Z001')->create();
        Movement::factory()->forStore('Z001')->create();
        Movement::factory()->forStore('Z002')->create();

        $this->assertSame(2, Movement::forStore('Z001')->count());
    }

    public function test_for_movement_code_scope(): void
    {
        Movement::factory()->count(3)->create(['movement_code' => 2]);
        Movement::factory()->count(2)->create(['movement_code' => 6]);

        $this->assertSame(2, Movement::forMovementCode(6)->count());
    }

    public function test_for_consultant_scope(): void
    {
        Movement::factory()->count(2)->create(['cpf_consultant' => '11111111111']);
        Movement::factory()->create(['cpf_consultant' => '22222222222']);

        $this->assertSame(2, Movement::forConsultant('11111111111')->count());
    }

    public function test_calculate_net_values_sale_exit(): void
    {
        [$netValue, $netQty] = Movement::calculateNetValues(100.00, 2.0, 2, 'S');

        $this->assertSame(100.00, $netValue);
        $this->assertSame(-2.0, $netQty);
    }

    public function test_calculate_net_values_return_entry_inverts_value(): void
    {
        [$netValue, $netQty] = Movement::calculateNetValues(80.00, 1.0, 6, 'E');

        $this->assertSame(-80.00, $netValue);
        $this->assertSame(1.0, $netQty);
    }

    public function test_calculate_net_values_regular_entry(): void
    {
        [$netValue, $netQty] = Movement::calculateNetValues(50.00, 3.0, 1, 'E');

        $this->assertSame(50.00, $netValue);
        $this->assertSame(3.0, $netQty);
    }

    public function test_calculate_net_values_handles_lowercase_entry_exit(): void
    {
        [$netValue, $netQty] = Movement::calculateNetValues(100.00, 2.0, 6, 'e');

        $this->assertSame(-100.00, $netValue);
        $this->assertSame(2.0, $netQty);
    }
}
