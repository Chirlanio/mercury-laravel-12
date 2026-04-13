<?php

namespace Database\Factories;

use App\Models\HdDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HdDepartment>
 */
class HdDepartmentFactory extends Factory
{
    protected $model = HdDepartment::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'icon' => 'fas fa-headset',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
