<?php

namespace Database\Seeders;

use App\Models\CentralModule;
use Illuminate\Database\Seeder;

class CentralModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = config('modules', []);
        $order = 0;

        foreach ($modules as $slug => $definition) {
            CentralModule::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'icon' => $definition['icon'] ?? null,
                    'routes' => $definition['routes'] ?? [],
                    'dependencies' => $definition['dependencies'] ?? null,
                    'is_active' => true,
                    'sort_order' => $order++,
                ]
            );
        }
    }
}
