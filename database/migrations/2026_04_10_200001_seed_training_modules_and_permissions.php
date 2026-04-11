<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CentralModule;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds training and experience-tracker modules, permissions, and plan assignments
 * for existing installations. For new installations, the seeders handle this.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Seed central modules from config
        $this->seedModules();

        // 2. Seed permissions from enum
        $this->seedPermissions();

        // 3. Seed role-permission assignments
        $this->seedRolePermissions();

        // 4. Add modules to existing plans
        $this->addModulesToPlans();
    }

    public function down(): void
    {
        // Remove plan module assignments
        DB::table('tenant_modules')
            ->whereIn('module_slug', ['training', 'experience-tracker'])
            ->delete();

        // Remove role-permission assignments for training permissions
        $trainingSlugs = collect(Permission::cases())
            ->filter(fn ($p) => str_starts_with($p->value, 'training') || str_starts_with($p->value, 'experience_tracker'))
            ->pluck('value')
            ->toArray();

        $permIds = CentralPermission::whereIn('slug', $trainingSlugs)->pluck('id')->toArray();
        if (! empty($permIds)) {
            DB::table('central_role_permissions')->whereIn('central_permission_id', $permIds)->delete();
        }

        // Remove permissions
        CentralPermission::whereIn('slug', $trainingSlugs)->delete();

        // Remove modules
        CentralModule::whereIn('slug', ['training', 'experience-tracker'])->delete();
    }

    private function seedModules(): void
    {
        $modules = config('modules', []);

        foreach (['training', 'experience-tracker'] as $slug) {
            if (! isset($modules[$slug])) {
                continue;
            }

            $def = $modules[$slug];
            CentralModule::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $def['name'],
                    'description' => $def['description'] ?? null,
                    'icon' => $def['icon'] ?? null,
                    'routes' => $def['routes'] ?? [],
                    'dependencies' => $def['dependencies'] ?? null,
                    'is_active' => true,
                    'sort_order' => CentralModule::max('sort_order') + 1,
                ]
            );
        }
    }

    private function seedPermissions(): void
    {
        $trainingPermissions = collect(Permission::cases())
            ->filter(fn ($p) => str_starts_with($p->value, 'training') || str_starts_with($p->value, 'experience_tracker'));

        foreach ($trainingPermissions as $perm) {
            $group = explode('.', $perm->value)[0];
            CentralPermission::updateOrCreate(
                ['slug' => $perm->value],
                [
                    'label' => $perm->label(),
                    'description' => $perm->description(),
                    'group' => $group,
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedRolePermissions(): void
    {
        $roles = CentralRole::all()->keyBy('name');
        $permissions = CentralPermission::all()->keyBy('slug');

        foreach (Role::cases() as $roleEnum) {
            $role = $roles[$roleEnum->value] ?? null;
            if (! $role) {
                continue;
            }

            // Get all permission slugs for this role (including training ones)
            $permSlugs = $roleEnum->permissions();
            $permIds = $permissions->filter(fn ($p) => in_array($p->slug, $permSlugs))->pluck('id')->toArray();

            // Sync replaces all, so use syncWithoutDetaching to only add new ones
            $role->permissions()->syncWithoutDetaching($permIds);
        }
    }

    private function addModulesToPlans(): void
    {
        $plans = DB::table('tenant_plans')->get();

        foreach ($plans as $plan) {
            foreach (['training', 'experience-tracker'] as $moduleSlug) {
                $exists = DB::table('tenant_modules')
                    ->where('plan_id', $plan->id)
                    ->where('module_slug', $moduleSlug)
                    ->exists();

                if (! $exists) {
                    // Enable for Professional and Enterprise, disable for Starter
                    $isEnabled = in_array($plan->slug, ['professional', 'enterprise']);

                    DB::table('tenant_modules')->insert([
                        'plan_id' => $plan->id,
                        'module_slug' => $moduleSlug,
                        'is_enabled' => $isEnabled,
                    ]);
                }
            }
        }
    }
};
