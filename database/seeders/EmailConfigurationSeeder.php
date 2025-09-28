<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmailConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('email_configurations')->insert([
            'name' => 'Portal Mercury',
            'email' => 'mercury@portalmercury.com.br',
            'host' => 'smtp.hostinger.com',
            'username' => 'mercury@portalmercury.com.br',
            'password' => 't5U-B2)0RK8n',
            'smtp_security' => 'tls',
            'port' => 587,
            'created_at' => Carbon::now(),
            'updated_at' => null,
        ]);
    }
}
