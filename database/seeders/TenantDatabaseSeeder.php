<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder for new tenant databases.
 * Seeds only the essential reference data — no company-specific data.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Reference/lookup data (generic, no FK dependencies)
            EmailConfigurationSeeder::class,
            ColorThemeSeeder::class,
            StatusSeeder::class,
            PageStatusSeeder::class,
            EmploymentRelationshipSeeder::class,
            EducationLevelSeeder::class,
            GenderSeeder::class,
            EmployeeStatusSeeder::class,
            EmployeeEventTypeSeeder::class,
            TypeMovimentSeeder::class,
            PositionLevelSeeder::class,

            // Menu and access control structure
            MenuSeeder::class,
            AdditionalAccessLevelsSeeder::class,
            PageGroupSeeder::class,
            PageSeeder::class,
            AccessLevelPageSeeder::class,
        ]);

        // Seed generic sectors without manager FK references
        $this->seedGenericSectors();
    }

    /**
     * Seed sectors without hardcoded manager references.
     * Managers are company-specific and added by each tenant later.
     */
    protected function seedGenericSectors(): void
    {
        $now = now();
        $sectors = [
            'Administrativo', 'Comercial', 'Financeiro', 'Logistica',
            'Marketing', 'Operacional', 'Recursos Humanos', 'Tecnologia',
        ];

        foreach ($sectors as $name) {
            \DB::table('sectors')->insertOrIgnore([
                'sector_name' => $name,
                'area_manager_id' => null,
                'sector_manager_id' => null,
                'is_active' => true,
                'created_at' => $now,
            ]);
        }
    }
}
