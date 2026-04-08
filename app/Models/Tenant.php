<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    // String-based IDs (slugs), not auto-incrementing integers
    public $incrementing = false;
    protected $keyType = 'string';

    // Override stancl's GeneratesIds trait which checks for a bound ID generator
    public function getIncrementing()
    {
        return false;
    }

    public function getKeyType()
    {
        return 'string';
    }

    protected $casts = [
        'settings' => 'json',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'cnpj',
            'plan_id',
            'is_active',
            'owner_name',
            'owner_email',
            'settings',
            'trial_ends_at',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(TenantPlan::class, 'plan_id');
    }

    public function invoices()
    {
        return $this->hasMany(TenantInvoice::class);
    }

    public function integrations()
    {
        return $this->hasMany(TenantIntegration::class);
    }

    public function modules()
    {
        return $this->plan ? $this->plan->modules() : collect();
    }

    public function hasModule(string $moduleSlug): bool
    {
        if (! $this->plan) {
            return false;
        }

        return $this->plan->modules()
            ->where('module_slug', $moduleSlug)
            ->where('is_enabled', true)
            ->exists();
    }

    public function activeModules()
    {
        if (! $this->plan) {
            return collect();
        }

        return $this->plan->modules()->where('is_enabled', true)->get();
    }

    public function isTrialing(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast() && ! $this->plan_id;
    }

    /**
     * Get the roles this tenant is allowed to assign to users.
     * Returns all role values when no restriction is configured (backward compatible).
     */
    public function getAllowedRoles(): array
    {
        $allowed = $this->settings['allowed_roles'] ?? null;

        if ($allowed === null || $allowed === []) {
            return array_map(fn ($case) => $case->value, \App\Enums\Role::cases());
        }

        return $allowed;
    }
}
