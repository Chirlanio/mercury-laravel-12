<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\CentralPermission;
use App\Models\CentralRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves role permissions from the central database.
 * Falls back to the PHP enum when no DB data is available.
 *
 * This allows the SaaS admin to modify role permissions via the central panel
 * without requiring code changes or deployments.
 */
class CentralRoleResolver
{
    /**
     * Get all permission slugs for a given role.
     */
    public function getPermissionsForRole(string $roleSlug): array
    {
        // Use the file store directly to avoid stancl/tenancy CacheManager
        // which adds tags — the database store does not support tagging.
        return Cache::store('file')->remember(
            "central_role_perms:{$roleSlug}",
            300, // 5 minutes
            function () use ($roleSlug) {
                try {
                    $role = CentralRole::on('mysql')
                        ->where('name', $roleSlug)
                        ->where('is_active', true)
                        ->first();

                    if ($role) {
                        return $role->permissions()
                            ->where('is_active', true)
                            ->pluck('slug')
                            ->toArray();
                    }
                } catch (\Exception $e) {
                    // DB not available, fall through to enum
                }

                // Fallback to enum
                $enumRole = Role::tryFrom($roleSlug);

                return $enumRole ? $enumRole->permissions() : [];
            }
        );
    }

    /**
     * Check if a role has a specific permission.
     */
    public function roleHasPermission(string $roleSlug, string $permissionSlug): bool
    {
        return in_array($permissionSlug, $this->getPermissionsForRole($roleSlug));
    }

    /**
     * Get the hierarchy level for a role.
     */
    public function getHierarchyLevel(string $roleSlug): int
    {
        try {
            $role = CentralRole::on('mysql')
                ->where('name', $roleSlug)
                ->where('is_active', true)
                ->first();

            if ($role) {
                return $role->hierarchy_level;
            }
        } catch (\Exception $e) {
            // Fall through to hardcoded
        }

        $hierarchy = [
            'user' => 1,
            'drivers' => 1,
            'support' => 2,
            'admin' => 3,
            'super_admin' => 4,
        ];

        return $hierarchy[$roleSlug] ?? 0;
    }

    /**
     * Get all active roles ordered by hierarchy.
     */
    public function getAllRoles(): Collection
    {
        try {
            $roles = CentralRole::on('mysql')
                ->active()
                ->ordered()
                ->get();

            if ($roles->isNotEmpty()) {
                return $roles;
            }
        } catch (\Exception $e) {
            // Fall through
        }

        // Fallback: build collection from enum
        return collect(Role::cases())->map(fn (Role $r) => (object) [
            'name' => $r->value,
            'label' => $r->label(),
            'hierarchy_level' => match ($r) {
                Role::SUPER_ADMIN => 10,
                Role::ADMIN => 9,
                Role::FINANCE, Role::ACCOUNTING, Role::FISCAL => 8,
                Role::SUPPORT => 2,
                Role::USER, Role::DRIVER => 1,
            },
            'is_system' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Get all role options as [name => label] array.
     */
    public function getRoleOptions(): array
    {
        return $this->getAllRoles()
            ->pluck('label', 'name')
            ->toArray();
    }

    /**
     * Get all active permissions grouped by group name.
     */
    public function getPermissionsGrouped(): array
    {
        try {
            $perms = CentralPermission::on('mysql')
                ->active()
                ->grouped()
                ->get();

            if ($perms->isNotEmpty()) {
                return $perms->groupBy('group')->toArray();
            }
        } catch (\Exception $e) {
            // Fall through
        }

        // Fallback from enum
        return collect(Permission::cases())
            ->map(fn (Permission $p) => (object) [
                'slug' => $p->value,
                'label' => $p->label(),
                'description' => $p->description(),
                'group' => explode('.', $p->value)[0],
            ])
            ->groupBy('group')
            ->toArray();
    }

    /**
     * Clear cached permissions for a role (call after updating permissions).
     */
    public function clearCache(?string $roleSlug = null): void
    {
        $cache = Cache::store('file');

        if ($roleSlug) {
            $cache->forget("central_role_perms:{$roleSlug}");
        } else {
            // Clear all role caches
            foreach (['super_admin', 'admin', 'support', 'user', 'drivers'] as $slug) {
                $cache->forget("central_role_perms:{$slug}");
            }

            // Also clear any custom roles
            try {
                CentralRole::on('mysql')->pluck('name')->each(function ($name) use ($cache) {
                    $cache->forget("central_role_perms:{$name}");
                });
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }
}
