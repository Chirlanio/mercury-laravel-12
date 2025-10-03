<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeEventTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $eventTypes = [
            [
                'id' => 1,
                'name' => 'Férias',
                'description' => 'Período de férias do funcionário',
                'requires_document' => false,
                'requires_date_range' => true,
                'requires_single_date' => false,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'name' => 'Licença',
                'description' => 'Licença do funcionário (médica, maternidade, etc.)',
                'requires_document' => true,
                'requires_date_range' => true,
                'requires_single_date' => false,
                'is_active' => true,
            ],
            [
                'id' => 3,
                'name' => 'Falta',
                'description' => 'Falta do funcionário',
                'requires_document' => false,
                'requires_date_range' => false,
                'requires_single_date' => true,
                'is_active' => true,
            ],
            [
                'id' => 4,
                'name' => 'Atestado Médico',
                'description' => 'Atestado médico do funcionário',
                'requires_document' => true,
                'requires_date_range' => true,
                'requires_single_date' => false,
                'is_active' => true,
            ],
        ];

        foreach ($eventTypes as $eventType) {
            DB::table('employee_event_types')->updateOrInsert(
                ['id' => $eventType['id']],
                $eventType
            );
        }
    }
}
