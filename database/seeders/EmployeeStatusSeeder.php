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
            ['id' => 1, 'description_name' => 'Pendente', 'adms_cor_id' => 8, 'created_at' => '2024-08-14 16:27:05', 'updated_at' => null],
            ['id' => 2, 'description_name' => 'Ativo', 'adms_cor_id' => 7, 'created_at' => '2024-08-14 16:27:05', 'updated_at' => null],
            ['id' => 3, 'description_name' => 'Inativo', 'adms_cor_id' => 2, 'created_at' => '2024-08-14 16:28:10', 'updated_at' => null],
            ['id' => 4, 'description_name' => 'Férias', 'adms_cor_id' => 1, 'created_at' => '2024-08-14 16:28:10', 'updated_at' => null],
            ['id' => 5, 'description_name' => 'Licença', 'adms_cor_id' => 3, 'created_at' => '2024-08-14 16:28:10', 'updated_at' => null],
        ];

        foreach ($employeeStatuses as $employeeStatus) {
            DB::table('employee_statuses')->updateOrInsert(
                ['id' => $employeeStatus['id']],
                [
                    'description_name' => $employeeStatus['description_name'],
                    'adms_cor_id' => $employeeStatus['adms_cor_id'],
                    'color_theme_id' => $employeeStatus['adms_cor_id'], // Usando mesmo valor temporariamente
                    'created_at' => $employeeStatus['created_at'],
                    'updated_at' => $employeeStatus['updated_at'],
                ]
            );
        }
    }
}
