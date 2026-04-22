<?php

namespace Database\Factories;

use App\Models\CostCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCenter>
 */
class CostCenterFactory extends Factory
{
    protected $model = CostCenter::class;

    public function definition(): array
    {
        return [
            'code' => '8.1.'.fake()->unique()->numerify('##'),
            'reduced_code' => null,
            'name' => fake()->words(2, true),
            'description' => null,
            'area_id' => null,
            'parent_id' => null,
            'default_accounting_class_id' => null,
            'manager_id' => null,
            'is_active' => true,
            'external_source' => null,
            'imported_at' => null,
        ];
    }
}
