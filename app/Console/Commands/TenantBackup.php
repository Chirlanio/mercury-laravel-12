<?php

namespace App\Console\Commands;

use App\Models\CentralActivityLog;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class TenantBackup extends Command
{
    protected $signature = 'tenant:backup
        {tenant? : Tenant ID/slug (omit for all tenants)}
        {--path= : Custom backup directory}
        {--compress : Compress with gzip}';

    protected $description = 'Backup tenant database(s) to SQL dump files';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->error($tenantId ? "Tenant '{$tenantId}' not found." : 'No active tenants found.');
            return self::FAILURE;
        }

        $backupDir = $this->option('path') ?? storage_path('backups/tenants');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $compress = $this->option('compress');
        $timestamp = now()->format('Y-m-d_His');
        $successCount = 0;

        foreach ($tenants as $tenant) {
            $dbName = $tenant->data['tenancy_db_name']
                ?? config('tenancy.database.prefix') . $tenant->id . config('tenancy.database.suffix');

            $filename = "{$tenant->id}_{$timestamp}.sql" . ($compress ? '.gz' : '');
            $filepath = $backupDir . '/' . $filename;

            $this->info("Backing up tenant '{$tenant->name}' (DB: {$dbName})...");

            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');
            $user = config('database.connections.mysql.username');
            $pass = config('database.connections.mysql.password');

            $dumpCmd = "mysqldump -h{$host} -P{$port} -u{$user}"
                . ($pass ? " -p{$pass}" : '')
                . " --single-transaction --routines --triggers {$dbName}";

            if ($compress) {
                $dumpCmd .= " | gzip";
            }

            $dumpCmd .= " > \"{$filepath}\"";

            $result = Process::timeout(300)->run($dumpCmd);

            if ($result->successful() && file_exists($filepath) && filesize($filepath) > 0) {
                $sizeMb = round(filesize($filepath) / 1024 / 1024, 2);
                $this->info("  -> {$filename} ({$sizeMb} MB)");
                $successCount++;

                CentralActivityLog::log('tenant.backup', "Backup de '{$tenant->name}': {$filename} ({$sizeMb} MB)", $tenant->id);
            } else {
                $this->error("  -> Failed: " . $result->errorOutput());
            }
        }

        $this->newLine();
        $this->info("Backup complete: {$successCount}/{$tenants->count()} tenants.");
        $this->info("Location: {$backupDir}");

        return $successCount === $tenants->count() ? self::SUCCESS : self::FAILURE;
    }
}
