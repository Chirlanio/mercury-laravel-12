<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing stores and employees
        $stores = DB::table('stores')->where('status_id', 1)->limit(5)->pluck('id')->toArray();
        $employees = DB::table('employees')->whereNull('dismissal_date')->limit(8)->pluck('id')->toArray();

        if (empty($stores) || empty($employees)) {
            return;
        }

        $records = [];
        $now = Carbon::now();

        // Generate 3 months of data: Dec 2025, Jan 2026, Feb 2026
        $periods = [
            ['year' => 2025, 'month' => 12],
            ['year' => 2026, 'month' => 1],
            ['year' => 2026, 'month' => 2],
        ];

        foreach ($periods as $period) {
            $daysInMonth = Carbon::create($period['year'], $period['month'])->daysInMonth;
            $maxDay = min($daysInMonth, $period['year'] == 2026 && $period['month'] == 2 ? 7 : $daysInMonth);

            // Generate ~15-20 records per month
            for ($day = 1; $day <= $maxDay; $day += rand(1, 2)) {
                $date = Carbon::create($period['year'], $period['month'], $day);

                if ($date->isFuture()) {
                    continue;
                }

                // 2-4 sales per day across different stores/employees
                $dailySales = rand(2, 4);
                $usedCombinations = [];

                for ($s = 0; $s < $dailySales; $s++) {
                    $storeId = $stores[array_rand($stores)];
                    $employeeId = $employees[array_rand($employees)];

                    $key = "{$storeId}-{$employeeId}";
                    if (in_array($key, $usedCombinations)) {
                        continue;
                    }
                    $usedCombinations[] = $key;

                    $records[] = [
                        'store_id' => $storeId,
                        'employee_id' => $employeeId,
                        'date_sales' => $date->format('Y-m-d'),
                        'total_sales' => round(rand(50000, 500000) / 100, 2),
                        'qtde_total' => rand(1, 25),
                        'source' => 'manual',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // Insert in chunks to avoid duplicates
        foreach ($records as $record) {
            DB::table('sales')->updateOrInsert(
                [
                    'store_id' => $record['store_id'],
                    'employee_id' => $record['employee_id'],
                    'date_sales' => $record['date_sales'],
                ],
                $record
            );
        }
    }
}
