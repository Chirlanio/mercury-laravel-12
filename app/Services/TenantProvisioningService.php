<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Tenant;
use App\Models\TenantPlan;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * Create a new tenant with database, migrations, and default data.
     *
     * @param array $data {
     *   name: string,
     *   slug: string,
     *   cnpj: ?string,
     *   plan_slug: ?string,
     *   owner_name: string,
     *   owner_email: string,
     *   domain: ?string,
     *   settings: ?array,
     *   trial_days: ?int,
     * }
     * @return Tenant
     */
    public function createTenant(array $data): Tenant
    {
        $slug = Str::slug($data['slug'] ?? $data['name']);

        $plan = null;
        if (! empty($data['plan_slug'])) {
            $plan = TenantPlan::where('slug', $data['plan_slug'])->first();
        }

        // Create the tenant (triggers CreateDatabase + MigrateDatabase + SeedDatabase via events)
        $tenant = Tenant::create([
            'id' => $slug,
            'name' => $data['name'],
            'slug' => $slug,
            'cnpj' => $data['cnpj'] ?? null,
            'plan_id' => $plan?->id,
            'is_active' => true,
            'owner_name' => $data['owner_name'],
            'owner_email' => $data['owner_email'],
            'settings' => $data['settings'] ?? [],
            'trial_ends_at' => isset($data['trial_days'])
                ? now()->addDays($data['trial_days'])
                : null,
        ]);

        // Store just the subdomain — InitializeTenancyBySubdomain extracts
        // the subdomain from the hostname and looks it up in the domains table
        $domainName = $data['domain'] ?? $slug;

        $tenant->domains()->create([
            'domain' => $domainName,
        ]);

        // Create the first admin user in the tenant database
        $tenant->run(function () use ($data) {
            $this->createTenantAdmin($data);
        });

        return $tenant;
    }

    /**
     * Create the initial admin user within the tenant's database.
     */
    protected function createTenantAdmin(array $data): void
    {
        $userModel = config('auth.providers.users.model');

        // Find the highest-privilege access level (Super Administrador = ID 1)
        $accessLevelId = \App\Models\AccessLevel::where('name', 'like', '%Super Admin%')
            ->orWhere('name', 'like', '%Super Administrador%')
            ->value('id') ?? 1;

        $userModel::create([
            'name' => $data['owner_name'],
            'email' => $data['owner_email'],
            'username' => Str::slug($data['owner_name'], '.'),
            'password' => bcrypt($data['admin_password'] ?? Str::random(16)),
            'role' => 'super_admin',
            'access_level_id' => $accessLevelId,
        ]);
    }

    /**
     * Suspend a tenant (disable access).
     */
    public function suspendTenant(Tenant $tenant): void
    {
        $tenant->update(['is_active' => false]);
    }

    /**
     * Reactivate a suspended tenant.
     */
    public function reactivateTenant(Tenant $tenant): void
    {
        $tenant->update(['is_active' => true]);
    }

    /**
     * Delete a tenant and its database.
     * Handles cases where the database doesn't exist (e.g. failed provisioning).
     */
    public function deleteTenant(Tenant $tenant): void
    {
        try {
            $tenant->delete(); // Triggers DeleteDatabase via events
        } catch (\Illuminate\Database\QueryException $e) {
            // If DROP DATABASE fails (db doesn't exist), still remove the tenant record
            if (str_contains($e->getMessage(), "Can't drop database") || str_contains($e->getMessage(), '1008')) {
                // Force delete without events to skip DeleteDatabase job
                $tenant->domains()->delete();
                $tenant->invoices()->delete();
                $tenant->integrations()->delete();
                \DB::table('tenants')->where('id', $tenant->id)->delete();
            } else {
                throw $e;
            }
        }
    }
}
