<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantMigrateLegacy extends Command
{
    protected $signature = 'tenant:migrate-legacy
        {--database= : The existing MySQL database name to adopt}
        {--slug=meia-sola : Slug/ID for the tenant}
        {--name=Grupo Meia Sola : Company name}
        {--email=admin@meiasola.com.br : Owner email}
        {--owner=Administrador : Owner name}
        {--plan=enterprise : Plan slug to assign}
        {--domain= : Full domain (e.g. meia-sola.mercury.localhost)}';

    protected $description = 'Migrate the existing (legacy) database as the first tenant without recreating it';

    public function handle(): int
    {
        $slug = $this->option('slug');
        $existingDb = $this->option('database');

        if (! $existingDb) {
            $existingDb = config('database.connections.mysql.database');
            $this->info("No --database specified, using current: {$existingDb}");
        }

        if (Tenant::find($slug)) {
            $this->error("Tenant '{$slug}' already exists.");
            return self::FAILURE;
        }

        // Verify the database exists
        try {
            $tables = DB::select("SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?", [$existingDb]);
            $tableCount = $tables[0]->cnt ?? 0;
            $this->info("Database '{$existingDb}' has {$tableCount} tables.");
        } catch (\Exception $e) {
            $this->error("Cannot access database '{$existingDb}': {$e->getMessage()}");
            return self::FAILURE;
        }

        $plan = TenantPlan::where('slug', $this->option('plan'))->first();

        $this->info("Creating tenant record for legacy database...");

        // Store just the subdomain — InitializeTenancyBySubdomain resolves it
        $domainName = $this->option('domain') ?? $slug;

        // stancl/tenancy uses the 'data' JSON column for tenancy_db_name
        // We need to set it so the tenant connection uses the existing DB
        $dbPrefix = config('tenancy.database.prefix', 'mercury_');
        $expectedDbName = $dbPrefix . $slug;

        $tenantData = [
            'tenancy_db_name' => $existingDb !== $expectedDbName ? $existingDb : null,
        ];

        // Insert the tenant record directly to avoid CreateDatabase/MigrateDatabase events
        DB::table('tenants')->insert([
            'id' => $slug,
            'name' => $this->option('name'),
            'slug' => $slug,
            'cnpj' => null,
            'plan_id' => $plan?->id,
            'is_active' => true,
            'owner_name' => $this->option('owner'),
            'owner_email' => $this->option('email'),
            'settings' => json_encode(['legacy_migration' => true, 'migrated_at' => now()->toIso8601String()]),
            'data' => json_encode(array_filter($tenantData)),
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        // Insert domain directly too
        DB::table('domains')->insert([
            'domain' => $domainName,
            'tenant_id' => $slug,
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->info("Legacy migration complete!");
        $this->table(
            ['Property', 'Value'],
            [
                ['Tenant ID', $slug],
                ['Name', $this->option('name')],
                ['Database', $existingDb],
                ['Expected DB', $expectedDbName],
                ['DB Override', $existingDb !== $expectedDbName ? 'Yes (stored in data column)' : 'No (name matches)'],
                ['Domain', $domainName],
                ['Plan', $plan?->name ?? 'None'],
            ]
        );

        $this->newLine();
        $this->info("The existing database '{$existingDb}' is now associated with tenant '{$slug}'.");
        $this->info("Access at: http://{$domainName}");

        return self::SUCCESS;
    }
}
