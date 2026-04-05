<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class TenantRestore extends Command
{
    protected $signature = 'tenant:restore
        {tenant : Tenant ID/slug}
        {file : Path to the SQL dump file}';

    protected $description = 'Restore a tenant database from a SQL dump file';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $file = $this->argument('file');

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return self::FAILURE;
        }

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $dbName = $tenant->data['tenancy_db_name']
            ?? config('tenancy.database.prefix') . $tenant->id . config('tenancy.database.suffix');

        if (! $this->confirm("Restore database '{$dbName}' for tenant '{$tenant->name}' from {$file}? This will OVERWRITE all current data.")) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        $this->info("Restoring '{$tenant->name}' (DB: {$dbName})...");

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $isCompressed = str_ends_with($file, '.gz');

        $restoreCmd = $isCompressed
            ? "gunzip < \"{$file}\" | mysql -h{$host} -P{$port} -u{$user}" . ($pass ? " -p{$pass}" : '') . " {$dbName}"
            : "mysql -h{$host} -P{$port} -u{$user}" . ($pass ? " -p{$pass}" : '') . " {$dbName} < \"{$file}\"";

        $result = Process::timeout(600)->run($restoreCmd);

        if ($result->successful()) {
            $this->info('Restore completed successfully.');
            return self::SUCCESS;
        }

        $this->error('Restore failed: ' . $result->errorOutput());
        return self::FAILURE;
    }
}
