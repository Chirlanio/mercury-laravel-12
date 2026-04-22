<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\DreActual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DreActual>
 */
class DreActualFactory extends Factory
{
    protected $model = DreActual::class;

    public function definition(): array
    {
        return [
            'entry_date' => '2026-01-15',
            'chart_of_account_id' => ChartOfAccount::factory(),
            'cost_center_id' => null,
            'store_id' => null,
            'amount' => fake()->randomFloat(2, -10000, 10000),
            'source' => DreActual::SOURCE_MANUAL_IMPORT,
            'source_type' => null,
            'source_id' => null,
            'document' => null,
            'description' => null,
            'external_id' => null,
            'reported_in_closed_period' => false,
            'imported_at' => null,
        ];
    }
}
