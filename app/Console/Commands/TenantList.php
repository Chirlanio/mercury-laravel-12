<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantList extends Command
{
    protected $signature = 'tenant:list';
    protected $description = 'List all tenants';

    public function handle(): int
    {
        $tenants = Tenant::with('plan', 'domains')->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return self::SUCCESS;
        }

        $rows = $tenants->map(fn ($t) => [
            $t->id,
            $t->name,
            $t->domains->first()?->domain ?? '-',
            $t->plan?->name ?? 'None',
            $t->is_active ? 'Active' : 'Inactive',
            $t->created_at->format('Y-m-d'),
        ]);

        $this->table(
            ['ID', 'Name', 'Domain', 'Plan', 'Status', 'Created'],
            $rows
        );

        return self::SUCCESS;
    }
}
