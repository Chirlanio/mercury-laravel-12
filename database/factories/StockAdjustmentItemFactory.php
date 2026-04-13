<?php

namespace Database\Factories;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustmentItem>
 */
class StockAdjustmentItemFactory extends Factory
{
    protected $model = StockAdjustmentItem::class;

    public function definition(): array
    {
        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'reference' => fake()->numerify('REF######'),
            'size' => (string) fake()->numberBetween(34, 44),
            'direction' => fake()->randomElement(['increase', 'decrease']),
            'quantity' => fake()->numberBetween(1, 10),
            'current_stock' => fake()->optional()->numberBetween(0, 100),
            'reason_id' => null,
            'notes' => null,
            'is_adjustment' => true,
            'sort_order' => 0,
        ];
    }

    public function increase(): static
    {
        return $this->state(fn () => ['direction' => 'increase']);
    }

    public function decrease(): static
    {
        return $this->state(fn () => ['direction' => 'decrease']);
    }
}
