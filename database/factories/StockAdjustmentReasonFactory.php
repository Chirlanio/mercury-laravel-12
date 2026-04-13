<?php

namespace Database\Factories;

use App\Models\StockAdjustmentReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustmentReason>
 */
class StockAdjustmentReasonFactory extends Factory
{
    protected $model = StockAdjustmentReason::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('REASON_?????')),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'applies_to' => 'both',
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
