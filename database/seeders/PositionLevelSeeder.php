<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PositionLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $positionLevels = [
            ['name' => 'Gerencial'],
            ['name' => 'Operacional'],
            ['name' => 'Aprendiz'],
        ];

        foreach ($positionLevels as $level) {
            DB::table('position_levels')->insert([
                'name' => $level['name'],
                'created_at' => $now,
                'updated_at' => null,
            ]);
        }
    }
}
