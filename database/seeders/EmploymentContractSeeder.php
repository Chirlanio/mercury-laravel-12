<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmploymentContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('employment_contracts')->insert([
            'employee_id' => 1,
            'position_id' => 2,
            'movement_type_id' => 1,
            'start_date' => '2020-01-01',
            'end_date' => null,
            'store_id' => 'Z999',
            'created_at' => Carbon::parse('2024-08-28 12:28:34'),
            'updated_at' => null,
        ]);
    }
}
