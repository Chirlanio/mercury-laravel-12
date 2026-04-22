<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\DreBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DreBudget>
 */
class DreBudgetFactory extends Factory
{
    protected $model = DreBudget::class;

    public function definition(): array
    {
        return [
            'entry_date' => '2026-01-01',
            'chart_of_account_id' => ChartOfAccount::factory(),
            'cost_center_id' => null,
            'store_id' => null,
            'amount' => fake()->randomFloat(2, -100000, 100000),
            'budget_version' => 'v1',
            'budget_upload_id' => null,
            'notes' => null,
        ];
    }
}
