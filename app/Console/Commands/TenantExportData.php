<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantExportData extends Command
{
    protected $signature = 'tenant:export-data
        {tenant : Tenant ID/slug}
        {--path= : Output directory}
        {--format=json : Export format (json)}';

    protected $description = 'Export all personal data for a tenant (LGPD compliance)';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if (! $tenant) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $outputDir = $this->option('path') ?? storage_path('exports/tenants');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $this->info("Exporting data for tenant '{$tenant->name}'...");

        $data = [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'cnpj' => $tenant->cnpj,
                'owner_name' => $tenant->owner_name,
                'owner_email' => $tenant->owner_email,
                'created_at' => $tenant->created_at->toIso8601String(),
            ],
            'exported_at' => now()->toIso8601String(),
            'data' => [],
        ];

        $tenant->run(function () use (&$data) {
            $data['data']['users'] = \App\Models\User::all(['id', 'name', 'email', 'role', 'created_at'])->toArray();

            if (class_exists(\App\Models\Employee::class)) {
                $data['data']['employees'] = \App\Models\Employee::all([
                    'id', 'name', 'short_name', 'cpf', 'admission_date', 'dismissal_date', 'birth_date',
                ])->toArray();
            }

            if (class_exists(\App\Models\Store::class)) {
                $data['data']['stores'] = \App\Models\Store::all(['id', 'name', 'code'])->toArray();
            }
        });

        $filename = "{$tenant->id}_export_" . now()->format('Y-m-d_His') . '.json';
        $filepath = $outputDir . '/' . $filename;

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $sizeMb = round(filesize($filepath) / 1024, 2);
        $this->info("Exported to: {$filepath} ({$sizeMb} KB)");

        return self::SUCCESS;
    }
}
