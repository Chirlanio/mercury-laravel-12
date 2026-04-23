<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CentralModule;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds do módulo Customers: central_modules + central_permissions +
 * central_role_permissions + tenant_modules. Segue o padrão
 * estabelecido por Consignments/Coupons — migration idempotente.
 *
 * A página + menu do sidebar ficam na Fase 5b (migration separada) pra
 * evitar menu "fantasma" antes do CustomerController existir.
 */
return new class extends Migration
{
    private const MODULE_SLUG = 'customers';
    private const PERMISSION_PREFIX = 'customers.';

    public function up(): void
    {
        $this->seedModule();
        $this->seedPermissions();
        $this->seedRolePermissions();
        $this->addModuleToPlans();
    }

    public function down(): void
    {
        DB::table('tenant_modules')
            ->where('module_slug', self::MODULE_SLUG)
            ->delete();

        $slugs = collect(Permission::cases())
            ->filter(fn ($p) => str_starts_with($p->value, self::PERMISSION_PREFIX))
            ->pluck('value')
            ->toArray();

        $permIds = CentralPermission::whereIn('slug', $slugs)->pluck('id')->toArray();
        if (! empty($permIds)) {
            DB::table('central_role_permissions')
                ->whereIn('central_permission_id', $permIds)
                ->delete();
        }
        CentralPermission::whereIn('slug', $slugs)->delete();

        CentralModule::where('slug', self::MODULE_SLUG)->delete();
    }

    private function seedModule(): void
    {
        $def = config('modules.'.self::MODULE_SLUG);

        if (! $def) {
            return;
        }

        CentralModule::updateOrCreate(
            ['slug' => self::MODULE_SLUG],
            [
                'name' => $def['name'],
                'description' => $def['description'] ?? null,
                'icon' => $def['icon'] ?? null,
                'routes' => $def['routes'] ?? [],
                'dependencies' => $def['dependencies'] ?? null,
                'is_active' => true,
                'sort_order' => (CentralModule::max('sort_order') ?? 0) + 1,
            ],
        );
    }

    private function seedPermissions(): void
    {
        $modulePerms = collect(Permission::cases())
            ->filter(fn ($p) => str_starts_with($p->value, self::PERMISSION_PREFIX));

        foreach ($modulePerms as $perm) {
            $group = explode('.', $perm->value)[0];
            CentralPermission::updateOrCreate(
                ['slug' => $perm->value],
                [
                    'label' => $perm->label(),
                    'description' => $perm->description(),
                    'group' => $group,
                    'is_active' => true,
                ],
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

            $permSlugs = collect($roleEnum->permissions())
                ->filter(fn ($slug) => str_starts_with($slug, self::PERMISSION_PREFIX))
                ->toArray();

            $permIds = $permissions
                ->filter(fn ($p) => in_array($p->slug, $permSlugs))
                ->pluck('id')
                ->toArray();

            if (empty($permIds)) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching($permIds);
        }
    }

    private function addModuleToPlans(): void
    {
        $plans = DB::table('tenant_plans')->get();

        foreach ($plans as $plan) {
            $exists = DB::table('tenant_modules')
                ->where('plan_id', $plan->id)
                ->where('module_slug', self::MODULE_SLUG)
                ->exists();

            if ($exists) {
                continue;
            }

            // Customers é base para outros módulos (Consignments M12,
            // relatórios). Disponível nos 3 planos.
            $isEnabled = true;

            DB::table('tenant_modules')->insert([
                'plan_id' => $plan->id,
                'module_slug' => self::MODULE_SLUG,
                'is_enabled' => $isEnabled,
            ]);
        }
    }
};
