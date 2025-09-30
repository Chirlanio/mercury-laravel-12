<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $genders = [
            ['description_name' => 'Feminino', 'is_active' => true],
            ['description_name' => 'Masculino', 'is_active' => true],
        ];

        foreach ($genders as $gender) {
            DB::table('genders')->updateOrInsert(
                ['description_name' => $gender['description_name']],
                [
                    'is_active' => $gender['is_active'],
                    'created_at' => $now,
                    'updated_at' => null,
                ]
            );
        }
    }
}
