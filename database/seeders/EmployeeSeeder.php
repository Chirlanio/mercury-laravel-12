<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('employees')->insert([
            'name' => 'ADMINISTRADOR',
            'short_name' => 'ADMIN',
            'profile_image' => 'gms.jpg',
            'cpf' => '12345678945',
            'admission_date' => '2020-01-01',
            'dismissal_date' => null,
            'position_id' => 2,
            'site_coupon' => '',
            'store_id' => 'Z999',
            'education_level_id' => 8,
            'gender_id' => 2,
            'birth_date' => '2020-01-01',
            'area_id' => 12,
            'is_pcd' => false,
            'is_apprentice' => false,
            'level' => 'Senior',
            'status_id' => 2,
            'created_at' => Carbon::parse('2020-12-04 14:12:09'),
            'updated_at' => Carbon::parse('2025-06-23 16:54:15'),
        ]);
    }
}
