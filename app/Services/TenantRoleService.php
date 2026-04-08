<?php

namespace App\Services;

use App\Enums\Role;

class TenantRoleService
{
    /**
     * Get the allowed role options for the current tenant.
     * Returns all roles if no tenant context or no restriction configured.
     */
    public function getAllowedRoleOptions(): array
    {
        $tenant = tenant();

        if (! $tenant) {
            return Role::options();
        }

        $allowed = $tenant->getAllowedRoles();

        return collect(Role::options())
            ->filter(fn ($label, $value) => in_array($value, $allowed))
            ->toArray();
    }

    /**
     * Get the allowed role values as a comma-separated string for validation rules.
     */
    public function getAllowedRoleValues(): string
    {
        $tenant = tenant();

        if (! $tenant) {
            return implode(',', array_column(Role::cases(), 'value'));
        }

        return implode(',', $tenant->getAllowedRoles());
    }
}
