<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $sectors = [
            ['sector_name' => 'Qualidade', 'area_manager_id' => 1, 'sector_manager_id' => 2, 'is_active' => true],
            ['sector_name' => 'Controladoria', 'area_manager_id' => 2, 'sector_manager_id' => 3, 'is_active' => true],
            ['sector_name' => 'E-commerce', 'area_manager_id' => 3, 'sector_manager_id' => 4, 'is_active' => true],
            ['sector_name' => 'Lojas', 'area_manager_id' => 4, 'sector_manager_id' => 5, 'is_active' => true],
            ['sector_name' => 'Marketing', 'area_manager_id' => 5, 'sector_manager_id' => 6, 'is_active' => true],
            ['sector_name' => 'Financeiro', 'area_manager_id' => 6, 'sector_manager_id' => 7, 'is_active' => true],
            ['sector_name' => 'Recursos Humanos', 'area_manager_id' => 7, 'sector_manager_id' => 8, 'is_active' => true],
            ['sector_name' => 'Tecnologia da Informação', 'area_manager_id' => 8, 'sector_manager_id' => 9, 'is_active' => true],
            ['sector_name' => 'Logística', 'area_manager_id' => 9, 'sector_manager_id' => 10, 'is_active' => true],
        ];

        foreach ($sectors as $sector) {
            DB::table('sectors')->updateOrInsert(
                ['sector_name' => $sector['sector_name']],
                [
                    'area_manager_id' => $sector['area_manager_id'],
                    'sector_manager_id' => $sector['sector_manager_id'],
                    'is_active' => $sector['is_active'],
                    'created_at' => $now,
                    'updated_at' => null,
                ]
            );
        }
    }
}
