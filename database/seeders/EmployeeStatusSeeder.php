<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employeeStatuses = [
            ['description_name' => 'Pendente', 'color_theme_id' => 8, 'created_at' => '2024-08-14 16:27:05', 'updated_at' => null],
            ['description_name' => 'Ativo', 'color_theme_id' => 7, 'created_at' => '2024-08-14 16:27:05', 'updated_at' => null],
            ['description_name' => 'Inativo', 'color_theme_id' => 2, 'created_at' => '2024-08-14 16:28:10', 'updated_at' => null],
            ['description_name' => 'Férias', 'color_theme_id' => 1, 'created_at' => '2024-08-14 16:28:10', 'updated_at' => null],
            ['description_name' => 'Licença', 'color_theme_id' => 3, 'created_at' => '2024-08-14 16:28:10', 'updated_at' => null],
        ];

        foreach ($employeeStatuses as $employeeStatus) {
            DB::table('employee_statuses')->insert([
                'description_name' => $employeeStatus['description_name'],
                'color_theme_id' => $employeeStatus['color_theme_id'],
                'created_at' => $employeeStatus['created_at'],
                'updated_at' => $employeeStatus['updated_at'],
            ]);
        }
    }
}
