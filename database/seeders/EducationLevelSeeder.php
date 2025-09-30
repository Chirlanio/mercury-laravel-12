<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EducationLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $educationLevels = [
            ['description_name' => 'Ensino Fundamental Incompleto', 'is_active' => true],
            ['description_name' => 'Ensino Fundamental Completo', 'is_active' => true],
            ['description_name' => 'Ensino Médio Incompleto', 'is_active' => true],
            ['description_name' => 'Ensino Médio Completo', 'is_active' => true],
            ['description_name' => 'Educação Superior Incompleto', 'is_active' => true],
            ['description_name' => 'Educação Superior Completo', 'is_active' => true],
            ['description_name' => 'Pós-Graduação Incompleto', 'is_active' => true],
            ['description_name' => 'Pós-Graduação Completo', 'is_active' => true],
            ['description_name' => 'Mestrado', 'is_active' => true],
            ['description_name' => 'Doutorado', 'is_active' => true],
        ];

        foreach ($educationLevels as $level) {
            DB::table('education_levels')->updateOrInsert(
                ['description_name' => $level['description_name']],
                [
                    'is_active' => $level['is_active'],
                    'created_at' => $now,
                    'updated_at' => null,
                ]
            );
        }
    }
}
