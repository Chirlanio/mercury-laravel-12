<?php

namespace Database\Factories;

use App\Models\HdCategory;
use App\Models\HdDepartment;
use App\Models\HdTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HdCategory>
 */
class HdCategoryFactory extends Factory
{
    protected $model = HdCategory::class;

    public function definition(): array
    {
        return [
            'department_id' => HdDepartment::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
            'default_priority' => HdTicket::PRIORITY_MEDIUM,
        ];
    }

    public function forDepartment(HdDepartment $department): static
    {
        return $this->state(fn () => ['department_id' => $department->id]);
    }
}
