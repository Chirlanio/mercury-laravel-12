<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;

class PlanLimitService
{
    /**
     * Check if the tenant can create more users.
     */
    public function canCreateUser(?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?? tenant();

        if (! $tenant || ! $tenant->plan) {
            return true; // No plan = no limits (or handle differently)
        }

        $maxUsers = $tenant->plan->max_users;

        if ($maxUsers === 0) {
            return true; // 0 = unlimited
        }

        return User::count() < $maxUsers;
    }

    /**
     * Check if the tenant can create more stores.
     */
    public function canCreateStore(?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?? tenant();

        if (! $tenant || ! $tenant->plan) {
            return true;
        }

        $maxStores = $tenant->plan->max_stores;

        if ($maxStores === 0) {
            return true;
        }

        return Store::count() < $maxStores;
    }

    /**
     * Get current usage for a tenant.
     */
    public function getUsage(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? tenant();
        $plan = $tenant?->plan;

        $currentUsers = User::count();
        $currentStores = Store::count();

        return [
            'users' => [
                'current' => $currentUsers,
                'max' => $plan?->max_users ?? 0,
                'unlimited' => ! $plan || ($plan->max_users === 0),
                'percentage' => $plan && $plan->max_users > 0
                    ? round(($currentUsers / $plan->max_users) * 100)
                    : 0,
            ],
            'stores' => [
                'current' => $currentStores,
                'max' => $plan?->max_stores ?? 0,
                'unlimited' => ! $plan || ($plan->max_stores === 0),
                'percentage' => $plan && $plan->max_stores > 0
                    ? round(($currentStores / $plan->max_stores) * 100)
                    : 0,
            ],
        ];
    }

    /**
     * Get a human-readable limit message.
     */
    public function getLimitMessage(string $resource): string
    {
        $tenant = tenant();
        $plan = $tenant?->plan;

        if (! $plan) {
            return "Sem plano configurado.";
        }

        return match ($resource) {
            'users' => "Limite de {$plan->max_users} usuários atingido no plano {$plan->name}.",
            'stores' => "Limite de {$plan->max_stores} lojas atingido no plano {$plan->name}.",
            default => "Limite atingido no plano {$plan->name}.",
        };
    }
}
