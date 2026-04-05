<?php

namespace App\Console\Commands;

use App\Services\TenantProvisioningService;
use Illuminate\Console\Command;

class TenantCreate extends Command
{
    protected $signature = 'tenant:create
        {name : The tenant company name}
        {--slug= : URL slug (auto-generated from name if not provided)}
        {--email= : Owner email address}
        {--owner= : Owner full name}
        {--plan=professional : Plan slug (starter, professional, enterprise)}
        {--cnpj= : Company CNPJ}
        {--domain= : Custom domain (auto-generated if not provided)}
        {--password= : Initial admin password (auto-generated if not provided)}
        {--trial= : Trial period in days}';

    protected $description = 'Create a new tenant with database, migrations, and default data';

    public function handle(TenantProvisioningService $service): int
    {
        $name = $this->argument('name');
        $email = $this->option('email') ?? $this->ask('Owner email address');
        $ownerName = $this->option('owner') ?? $this->ask('Owner full name', $name);

        $this->info("Creating tenant: {$name}");

        try {
            $tenant = $service->createTenant([
                'name' => $name,
                'slug' => $this->option('slug'),
                'owner_name' => $ownerName,
                'owner_email' => $email,
                'plan_slug' => $this->option('plan'),
                'cnpj' => $this->option('cnpj'),
                'domain' => $this->option('domain'),
                'admin_password' => $this->option('password'),
                'trial_days' => $this->option('trial') ? (int) $this->option('trial') : null,
            ]);

            $this->info("Tenant created successfully!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $tenant->id],
                    ['Name', $tenant->name],
                    ['Slug', $tenant->slug],
                    ['Domain', $tenant->domains->first()?->domain ?? 'N/A'],
                    ['Plan', $tenant->plan?->name ?? 'None'],
                    ['Database', config('tenancy.database.prefix') . $tenant->id . config('tenancy.database.suffix')],
                    ['Admin Email', $email],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
