<?php

namespace Database\Seeders;

use App\Models\CentralUser;
use Illuminate\Database\Seeder;

class CentralUserSeeder extends Seeder
{
    public function run(): void
    {
        CentralUser::firstOrCreate(
            ['email' => 'admin@mercury.com.br'],
            [
                'name' => 'Mercury Admin',
                'password' => 'password',
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );
    }
}
