<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the central (master) database.
     * This seeds plan data and creates the initial central admin user.
     *
     * Tenant-specific data is seeded via TenantDatabaseSeeder
     * when a new tenant is created.
     */
    public function run(): void
    {
        $this->call([
            TenantPlanSeeder::class,
            CentralUserSeeder::class,
        ]);
    }
}
