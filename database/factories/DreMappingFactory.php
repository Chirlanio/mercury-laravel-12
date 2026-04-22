<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DreMapping>
 */
class DreMappingFactory extends Factory
{
    protected $model = DreMapping::class;

    public function definition(): array
    {
        return [
            'chart_of_account_id' => ChartOfAccount::factory(),
            'cost_center_id' => null,
            'dre_management_line_id' => DreManagementLine::factory(),
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'notes' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}
