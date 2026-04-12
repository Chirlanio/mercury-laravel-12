<?php

namespace App\Casts;

use App\Enums\Role;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts the role field to the Role enum when possible,
 * or returns a dynamic role object for custom roles from the central DB.
 */
class FlexibleRoleCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        // Try to resolve from enum first
        $role = Role::tryFrom($value);
        if ($role !== null) {
            return $role;
        }

        // Custom role from central DB — return a simple object with the same interface
        return new DynamicRole($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value instanceof Role) {
            return $value->value;
        }

        if ($value instanceof DynamicRole) {
            return $value->value;
        }

        return $value;
    }
}

/**
 * Represents a custom role that is not in the Role enum.
 * Provides the same interface as Role enum (->value, ->label()).
 */
class DynamicRole
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function label(): string
    {
        // Try to get label from central DB
        try {
            $role = \App\Models\CentralRole::on('mysql')
                ->where('name', $this->value)
                ->first();

            return $role?->label ?? ucfirst(str_replace('_', ' ', $this->value));
        } catch (\Exception $e) {
            return ucfirst(str_replace('_', ' ', $this->value));
        }
    }

    public function permissions(): array
    {
        try {
            $resolver = app(\App\Services\CentralRoleResolver::class);

            return $resolver->getPermissionsForRole($this->value);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function hasPermissionTo(\App\Enums\Permission|string $permission): bool
    {
        $permissionValue = $permission instanceof \App\Enums\Permission ? $permission->value : $permission;

        return in_array($permissionValue, $this->permissions());
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
