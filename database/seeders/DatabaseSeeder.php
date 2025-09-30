<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            EmailConfigurationSeeder::class,
            ColorThemeSeeder::class,
            StatusSeeder::class,
            PageStatusSeeder::class,
            EmploymentRelationshipSeeder::class,
            EducationLevelSeeder::class,
            GenderSeeder::class,
            ManagerSeeder::class,
            SectorSeeder::class,
            MenuSeeder::class,
            AdditionalAccessLevelsSeeder::class,
            PositionLevelSeeder::class,
            PositionSeeder::class,
            PageGroupSeeder::class,
            PageSeeder::class,
            AccessLevelPageSeeder::class,
            NetworkSeeder::class,
            StoreSeeder::class,
            EmployeeSeeder::class,
            EmploymentContractSeeder::class,
        ]);

        // Cria um usuário de teste comum
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ]
        );
    }
}
