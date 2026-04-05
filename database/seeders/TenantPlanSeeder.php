<?php

namespace Database\Seeders;

use App\Models\TenantModule;
use App\Models\TenantPlan;
use Illuminate\Database\Seeder;

class TenantPlanSeeder extends Seeder
{
    public function run(): void
    {
        // All available modules
        $allModules = [
            'dashboard', 'users', 'employees', 'stores', 'menus', 'pages',
            'access_levels', 'work_shifts', 'work_schedules', 'sales',
            'products', 'transfers', 'stock_adjustments', 'order_payments',
            'suppliers', 'checklists', 'medical_certificates', 'absences',
            'overtime', 'color_themes', 'activity_logs', 'user_sessions',
            'config', 'integrations',
        ];

        // Essential modules for Starter plan
        $starterModules = [
            'dashboard', 'users', 'employees', 'stores', 'menus', 'pages',
            'access_levels', 'sales', 'config',
        ];

        // Starter Plan
        $starter = TenantPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Plano inicial com módulos essenciais.',
            'max_users' => 10,
            'max_stores' => 1,
            'max_storage_mb' => 5120,
            'price_monthly' => 0,
            'price_yearly' => 0,
            'features' => ['support' => 'email', 'backup' => 'daily', 'reports' => 'basic'],
            'is_active' => true,
        ]);

        foreach ($allModules as $module) {
            TenantModule::create([
                'plan_id' => $starter->id,
                'module_slug' => $module,
                'is_enabled' => in_array($module, $starterModules),
            ]);
        }

        // Professional Plan
        $professional = TenantPlan::create([
            'name' => 'Professional',
            'slug' => 'professional',
            'description' => 'Plano completo com todos os módulos.',
            'max_users' => 50,
            'max_stores' => 10,
            'max_storage_mb' => 25600,
            'price_monthly' => 0,
            'price_yearly' => 0,
            'features' => ['support' => 'priority', 'backup' => 'daily_on_demand', 'reports' => 'advanced'],
            'is_active' => true,
        ]);

        foreach ($allModules as $module) {
            TenantModule::create([
                'plan_id' => $professional->id,
                'module_slug' => $module,
                'is_enabled' => true,
            ]);
        }

        // Enterprise Plan
        $enterprise = TenantPlan::create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'Plano enterprise com módulos ilimitados e customizações.',
            'max_users' => 0, // 0 = unlimited
            'max_stores' => 0,
            'max_storage_mb' => 102400,
            'price_monthly' => 0,
            'price_yearly' => 0,
            'features' => ['support' => 'dedicated', 'backup' => 'realtime', 'reports' => 'custom', 'api' => true, 'custom_modules' => true],
            'is_active' => true,
        ]);

        foreach ($allModules as $module) {
            TenantModule::create([
                'plan_id' => $enterprise->id,
                'module_slug' => $module,
                'is_enabled' => true,
            ]);
        }
    }
}
