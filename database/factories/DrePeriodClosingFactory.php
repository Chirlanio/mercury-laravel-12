<?php

namespace Database\Factories;

use App\Models\DrePeriodClosing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DrePeriodClosing>
 */
class DrePeriodClosingFactory extends Factory
{
    protected $model = DrePeriodClosing::class;

    public function definition(): array
    {
        return [
            'closed_up_to_date' => '2026-01-31',
            'closed_by_user_id' => User::factory(),
            'closed_at' => now(),
            'reopened_by_user_id' => null,
            'reopened_at' => null,
            'reopen_reason' => null,
            'notes' => null,
        ];
    }
}
