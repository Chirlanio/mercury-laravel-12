<?php

namespace App\Services;

use App\Enums\Role;

class TenantRoleService
{
    public function __construct(
        protected CentralRoleResolver $resolver,
    ) {}

    /**
     * Get the allowed role options for the current tenant.
     * Returns all roles if no tenant context or no restriction configured.
     */
    public function getAllowedRoleOptions(): array
    {
        $allRoles = $this->resolver->getRoleOptions();

        $tenant = tenant();

        if (! $tenant) {
            return $allRoles;
        }

        $allowed = $tenant->getAllowedRoles();

        return collect($allRoles)
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
            return implode(',', array_keys($this->resolver->getRoleOptions()));
        }

        return implode(',', $tenant->getAllowedRoles());
    }
}
