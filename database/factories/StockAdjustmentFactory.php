<?php

namespace Database\Factories;

use App\Models\StockAdjustment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'employee_id' => null,
            'status' => 'pending',
            'observation' => fake()->optional()->sentence(),
            'created_by_user_id' => User::factory(),
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function forStore(Store $store): static
    {
        return $this->state(fn () => ['store_id' => $store->id]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => ['created_by_user_id' => $user->id]);
    }
}
