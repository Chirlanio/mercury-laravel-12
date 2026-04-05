<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Console\Command;

class TenantDelete extends Command
{
    protected $signature = 'tenant:delete {id : The tenant ID/slug}';
    protected $description = 'Delete a tenant and its database';

    public function handle(TenantProvisioningService $service): int
    {
        $tenant = Tenant::find($this->argument('id'));

        if (! $tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        if (! $this->confirm("Delete tenant '{$tenant->name}' and ALL its data? This cannot be undone.")) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        try {
            $service->deleteTenant($tenant);
            $this->info("Tenant '{$tenant->name}' deleted successfully.");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
