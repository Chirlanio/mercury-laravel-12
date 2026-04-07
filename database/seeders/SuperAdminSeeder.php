<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'superadmin@mercury.com'],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@mercury.com',
                'password' => Hash::make('password'),
                'role' => Role::SUPER_ADMIN,
                'access_level_id' => 1,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@mercury.com'],
            [
                'name' => 'Admin',
                'email' => 'admin@mercury.com',
                'password' => Hash::make('password'),
                'role' => Role::ADMIN,
                'access_level_id' => 2,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'support@mercury.com'],
            [
                'name' => 'Support',
                'email' => 'support@mercury.com',
                'password' => Hash::make('password'),
                'role' => Role::SUPPORT,
                'access_level_id' => 3,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'user@mercury.com'],
            [
                'name' => 'User',
                'email' => 'user@mercury.com',
                'password' => Hash::make('password'),
                'role' => Role::USER,
                'access_level_id' => 18,
                'email_verified_at' => now(),
            ]
        );
    }
}
