<?php

namespace Database\Seeders;

use App\Models\PercentageAward;
use Illuminate\Database\Seeder;

class PercentageAwardSeeder extends Seeder
{
    public function run(): void
    {
        $awards = [
            ['level' => 'Júnior', 'no_goal_pct' => 1.00, 'goal_pct' => 2.00, 'super_goal_pct' => 2.50, 'hiper_goal_pct' => 3.00],
            ['level' => 'Pleno', 'no_goal_pct' => 1.50, 'goal_pct' => 2.50, 'super_goal_pct' => 3.00, 'hiper_goal_pct' => 3.50],
            ['level' => 'Sênior', 'no_goal_pct' => 2.00, 'goal_pct' => 3.00, 'super_goal_pct' => 3.50, 'hiper_goal_pct' => 4.00],
        ];

        foreach ($awards as $award) {
            PercentageAward::firstOrCreate(
                ['level' => $award['level']],
                $award
            );
        }
    }
}
