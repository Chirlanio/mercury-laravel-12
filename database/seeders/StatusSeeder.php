<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'Ativo', 'color_theme_id' => 7, 'created_at' => '2020-05-23 00:00:00', 'updated_at' => null],
            ['name' => 'Inativo', 'color_theme_id' => 2, 'created_at' => '2020-05-23 00:00:00', 'updated_at' => null],
            ['name' => 'Analise', 'color_theme_id' => 3, 'created_at' => '2020-05-23 00:00:00', 'updated_at' => '2021-01-07 21:02:47'],
        ];

        foreach ($statuses as $status) {
            DB::table('statuses')->updateOrInsert(
                ['name' => $status['name']],
                [
                    'color_theme_id' => $status['color_theme_id'],
                    'created_at' => $status['created_at'],
                    'updated_at' => $status['updated_at'],
                ]
            );
        }
    }
}
