<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use Illuminate\Database\Seeder;

/**
 * Seeds central_roles and central_permissions from the existing PHP enums.
 * This ensures the DB starts with the exact same data as the enums.
 */
class CentralRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();
        $this->seedPermissions();
        $this->seedRolePermissions();
    }

    protected function seedRoles(): void
    {
        $roles = [
            ['name' => 'super_admin', 'label' => 'Super Administrador', 'hierarchy_level' => 4, 'is_system' => true],
            ['name' => 'admin', 'label' => 'Administrador', 'hierarchy_level' => 3, 'is_system' => true],
            ['name' => 'support', 'label' => 'Suporte', 'hierarchy_level' => 2, 'is_system' => true],
            ['name' => 'user', 'label' => 'Usuário', 'hierarchy_level' => 1, 'is_system' => true],
        ];

        foreach ($roles as $role) {
            CentralRole::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }

    protected function seedPermissions(): void
    {
        // Extract group from slug (e.g., 'sales.view' → 'sales')
        foreach (Permission::cases() as $perm) {
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

    protected function seedRolePermissions(): void
    {
        $roles = CentralRole::all()->keyBy('name');
        $permissions = CentralPermission::all()->keyBy('slug');

        foreach (Role::cases() as $roleEnum) {
            $role = $roles[$roleEnum->value] ?? null;
            if (! $role) {
                continue;
            }

            $permSlugs = $roleEnum->permissions();
            $permIds = $permissions->only($permSlugs)->pluck('id')->toArray();

            $role->permissions()->sync($permIds);
        }
    }
}
