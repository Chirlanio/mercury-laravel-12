<?php

namespace Database\Seeders;

use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class WorkScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $schedules = [
            [
                'name' => 'COMERCIAL 5X2',
                'description' => 'Escala comercial padrão - Segunda a Sexta',
                'is_active' => true,
                'is_default' => true,
                'days' => [
                    ['day' => 0, 'work' => false],
                    ['day' => 1, 'work' => true, 'entry' => '08:00', 'exit' => '17:48', 'bs' => '12:00', 'be' => '13:00', 'bd' => 60],
                    ['day' => 2, 'work' => true, 'entry' => '08:00', 'exit' => '17:48', 'bs' => '12:00', 'be' => '13:00', 'bd' => 60],
                    ['day' => 3, 'work' => true, 'entry' => '08:00', 'exit' => '17:48', 'bs' => '12:00', 'be' => '13:00', 'bd' => 60],
                    ['day' => 4, 'work' => true, 'entry' => '08:00', 'exit' => '17:48', 'bs' => '12:00', 'be' => '13:00', 'bd' => 60],
                    ['day' => 5, 'work' => true, 'entry' => '08:00', 'exit' => '17:48', 'bs' => '12:00', 'be' => '13:00', 'bd' => 60],
                    ['day' => 6, 'work' => false],
                ],
            ],
            [
                'name' => 'SHOPPING 6X1',
                'description' => 'Escala de shopping - Segunda a Sábado',
                'is_active' => true,
                'is_default' => false,
                'days' => [
                    ['day' => 0, 'work' => false],
                    ['day' => 1, 'work' => true, 'entry' => '10:00', 'exit' => '18:20', 'bs' => '13:00', 'be' => '14:00', 'bd' => 60],
                    ['day' => 2, 'work' => true, 'entry' => '10:00', 'exit' => '18:20', 'bs' => '13:00', 'be' => '14:00', 'bd' => 60],
                    ['day' => 3, 'work' => true, 'entry' => '10:00', 'exit' => '18:20', 'bs' => '13:00', 'be' => '14:00', 'bd' => 60],
                    ['day' => 4, 'work' => true, 'entry' => '10:00', 'exit' => '18:20', 'bs' => '13:00', 'be' => '14:00', 'bd' => 60],
                    ['day' => 5, 'work' => true, 'entry' => '10:00', 'exit' => '18:20', 'bs' => '13:00', 'be' => '14:00', 'bd' => 60],
                    ['day' => 6, 'work' => true, 'entry' => '10:00', 'exit' => '18:20', 'bs' => '13:00', 'be' => '14:00', 'bd' => 60],
                ],
            ],
            [
                'name' => 'SHOPPING ESTENDIDO 6X1',
                'description' => 'Escala de shopping estendida - Segunda a Sábado com intervalo maior',
                'is_active' => true,
                'is_default' => false,
                'days' => [
                    ['day' => 0, 'work' => false],
                    ['day' => 1, 'work' => true, 'entry' => '09:00', 'exit' => '18:50', 'bs' => '12:00', 'be' => '13:30', 'bd' => 90],
                    ['day' => 2, 'work' => true, 'entry' => '09:00', 'exit' => '18:50', 'bs' => '12:00', 'be' => '13:30', 'bd' => 90],
                    ['day' => 3, 'work' => true, 'entry' => '09:00', 'exit' => '18:50', 'bs' => '12:00', 'be' => '13:30', 'bd' => 90],
                    ['day' => 4, 'work' => true, 'entry' => '09:00', 'exit' => '18:50', 'bs' => '12:00', 'be' => '13:30', 'bd' => 90],
                    ['day' => 5, 'work' => true, 'entry' => '09:00', 'exit' => '18:50', 'bs' => '12:00', 'be' => '13:30', 'bd' => 90],
                    ['day' => 6, 'work' => true, 'entry' => '09:00', 'exit' => '18:50', 'bs' => '12:00', 'be' => '13:30', 'bd' => 90],
                ],
            ],
        ];

        foreach ($schedules as $scheduleData) {
            $existingSchedule = WorkSchedule::where('name', $scheduleData['name'])->first();
            if ($existingSchedule) {
                continue;
            }

            $weeklyHours = 0;
            foreach ($scheduleData['days'] as $day) {
                if ($day['work']) {
                    $entry = strtotime($day['entry']);
                    $exit = strtotime($day['exit']);
                    $totalMin = ($exit - $entry) / 60 - $day['bd'];
                    $weeklyHours += $totalMin / 60;
                }
            }

            $schedule = WorkSchedule::create([
                'name' => $scheduleData['name'],
                'description' => $scheduleData['description'],
                'weekly_hours' => round($weeklyHours, 2),
                'is_active' => $scheduleData['is_active'],
                'is_default' => $scheduleData['is_default'],
                'created_by_user_id' => 1,
                'updated_by_user_id' => 1,
            ]);

            foreach ($scheduleData['days'] as $dayData) {
                $dailyHours = 0;
                if ($dayData['work']) {
                    $entry = strtotime($dayData['entry']);
                    $exit = strtotime($dayData['exit']);
                    $dailyHours = round((($exit - $entry) / 60 - $dayData['bd']) / 60, 2);
                }

                WorkScheduleDay::create([
                    'work_schedule_id' => $schedule->id,
                    'day_of_week' => $dayData['day'],
                    'is_work_day' => $dayData['work'],
                    'entry_time' => $dayData['work'] ? $dayData['entry'] : null,
                    'exit_time' => $dayData['work'] ? $dayData['exit'] : null,
                    'break_start' => $dayData['work'] ? $dayData['bs'] : null,
                    'break_end' => $dayData['work'] ? $dayData['be'] : null,
                    'break_duration_minutes' => $dayData['work'] ? $dayData['bd'] : null,
                    'daily_hours' => $dailyHours,
                ]);
            }
        }
    }
}
