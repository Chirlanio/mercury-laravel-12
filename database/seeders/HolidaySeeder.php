<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            // Feriados nacionais fixos (recorrentes)
            ['name' => 'Confraternização Universal', 'date' => '2026-01-01', 'type' => 'nacional', 'is_recurring' => true],
            ['name' => 'Tiradentes', 'date' => '2026-04-21', 'type' => 'nacional', 'is_recurring' => true],
            ['name' => 'Dia do Trabalho', 'date' => '2026-05-01', 'type' => 'nacional', 'is_recurring' => true],
            ['name' => 'Independência do Brasil', 'date' => '2026-09-07', 'type' => 'nacional', 'is_recurring' => true],
            ['name' => 'Nossa Sra. Aparecida', 'date' => '2026-10-12', 'type' => 'nacional', 'is_recurring' => true],
            ['name' => 'Finados', 'date' => '2026-11-02', 'type' => 'nacional', 'is_recurring' => true],
            ['name' => 'Proclamação da República', 'date' => '2026-11-15', 'type' => 'nacional', 'is_recurring' => true],
            ['name' => 'Natal', 'date' => '2026-12-25', 'type' => 'nacional', 'is_recurring' => true],

            // Feriados móveis 2026 (não recorrentes)
            ['name' => 'Carnaval', 'date' => '2026-02-17', 'type' => 'nacional', 'is_recurring' => false, 'year' => 2026],
            ['name' => 'Sexta-feira Santa', 'date' => '2026-04-03', 'type' => 'nacional', 'is_recurring' => false, 'year' => 2026],
            ['name' => 'Corpus Christi', 'date' => '2026-06-04', 'type' => 'nacional', 'is_recurring' => false, 'year' => 2026],
        ];

        foreach ($holidays as $holiday) {
            Holiday::firstOrCreate(
                ['name' => $holiday['name'], 'date' => $holiday['date']],
                array_merge($holiday, ['is_active' => true])
            );
        }

        $this->command->info('Feriados cadastrados: ' . Holiday::count());
    }
}
