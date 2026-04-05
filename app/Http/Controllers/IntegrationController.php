<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSyncLog;
use App\Models\TenantIntegration;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class IntegrationController extends Controller
{
    public function __construct(
        protected IntegrationManager $manager,
    ) {}

    public function index()
    {
        $tenant = tenant();

        $integrations = TenantIntegration::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($integration) => [
                'id' => $integration->id,
                'name' => $integration->name,
                'provider' => $integration->provider,
                'type' => $integration->type,
                'driver' => $integration->driver,
                'is_active' => $integration->is_active,
                'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
                'last_sync_status' => $integration->last_sync_status,
                'last_sync_message' => $integration->last_sync_message,
                'sync_schedule' => $integration->sync_schedule,
                'created_at' => $integration->created_at->toIso8601String(),
            ]);

        return Inertia::render('Integrations/Index', [
            'integrations' => $integrations,
            'providers' => IntegrationManager::providerPresets(),
            'drivers' => IntegrationManager::availableDrivers(),
        ]);
    }

    public function show(int $integration)
    {
        $tenant = tenant();

        $integration = TenantIntegration::where('tenant_id', $tenant->id)
            ->findOrFail($integration);

        $recentLogs = IntegrationSyncLog::where('integration_id', $integration->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $configSchema = IntegrationManager::getConfigSchema($integration->driver);

        return Inertia::render('Integrations/Show', [
            'integration' => [
                'id' => $integration->id,
                'name' => $integration->name,
                'provider' => $integration->provider,
                'type' => $integration->type,
                'driver' => $integration->driver,
                'is_active' => $integration->is_active,
                'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
                'last_sync_status' => $integration->last_sync_status,
                'last_sync_message' => $integration->last_sync_message,
                'sync_schedule' => $integration->sync_schedule,
                'config' => $this->maskSensitiveConfig($integration->config ?? []),
            ],
            'configSchema' => $configSchema,
            'recentLogs' => $recentLogs,
        ]);
    }

    public function store(Request $request)
    {
        $tenant = tenant();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|string|max:50',
            'type' => 'required|string|max:50',
            'driver' => 'required|string|max:50',
            'config' => 'required|array',
            'sync_schedule' => 'nullable|string|max:100',
        ]);

        // Validate config against driver schema
        $driverClass = IntegrationManager::availableDrivers()[$validated['driver']] ?? null;
        if (! $driverClass) {
            return back()->with('error', 'Driver inválido.');
        }

        $integration = TenantIntegration::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'provider' => $validated['provider'],
            'type' => $validated['type'],
            'driver' => $validated['driver'],
            'config' => $validated['config'],
            'sync_schedule' => $validated['sync_schedule'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('integrations.show', $integration->id)
            ->with('success', 'Integração criada com sucesso.');
    }

    public function update(Request $request, int $integration)
    {
        $tenant = tenant();

        $integration = TenantIntegration::where('tenant_id', $tenant->id)
            ->findOrFail($integration);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'config' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
            'sync_schedule' => 'nullable|string|max:100',
        ]);

        if (isset($validated['config'])) {
            // Merge with existing config, preserving passwords that weren't changed
            $existingConfig = $integration->config ?? [];
            $newConfig = $validated['config'];

            foreach ($newConfig as $key => $value) {
                if ($value === '********' && isset($existingConfig[$key])) {
                    $newConfig[$key] = $existingConfig[$key];
                }
            }

            $validated['config'] = $newConfig;
        }

        $integration->update($validated);

        return back()->with('success', 'Integração atualizada com sucesso.');
    }

    public function destroy(int $integration)
    {
        $tenant = tenant();

        $integration = TenantIntegration::where('tenant_id', $tenant->id)
            ->findOrFail($integration);

        $integration->delete();

        return redirect()->route('integrations.index')
            ->with('success', 'Integração excluída com sucesso.');
    }

    public function testConnection(int $integration)
    {
        $tenant = tenant();

        $integration = TenantIntegration::where('tenant_id', $tenant->id)
            ->findOrFail($integration);

        try {
            $driver = $this->manager->resolve($integration);
            $result = $driver->testConnection();

            return back()->with(
                $result['success'] ? 'success' : 'error',
                $result['message']
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao testar conexão: ' . $e->getMessage());
        }
    }

    public function triggerSync(Request $request, int $integration)
    {
        $tenant = tenant();

        $integration = TenantIntegration::where('tenant_id', $tenant->id)
            ->findOrFail($integration);

        if (! $integration->is_active) {
            return back()->with('error', 'Integração está desativada.');
        }

        $syncLog = IntegrationSyncLog::create([
            'integration_id' => $integration->id,
            'tenant_id' => $tenant->id,
            'direction' => 'pull',
            'status' => 'running',
            'started_at' => now(),
            'triggered_by' => auth()->user()?->email ?? 'manual',
        ]);

        try {
            $driver = $this->manager->resolve($integration);
            $options = $request->only(['date_from', 'date_to', 'store_id', 'resource']);
            $result = $driver->pull($options);

            $syncLog->update([
                'status' => empty($result['errors']) ? 'success' : 'error',
                'records_processed' => $result['processed'],
                'records_created' => $result['created'],
                'records_updated' => $result['updated'],
                'records_failed' => $result['failed'],
                'error_messages' => $result['errors'] ?: null,
                'finished_at' => now(),
            ]);

            if (empty($result['errors'])) {
                $integration->markSyncSuccess("Processados: {$result['processed']}, Criados: {$result['created']}, Atualizados: {$result['updated']}");
            } else {
                $integration->markSyncError(implode('; ', $result['errors']));
            }

            $message = "Sincronização concluída. Processados: {$result['processed']}, Criados: {$result['created']}, Atualizados: {$result['updated']}.";
            if ($result['failed'] > 0) {
                $message .= " Falhas: {$result['failed']}.";
            }

            return back()->with(
                empty($result['errors']) ? 'success' : 'warning',
                $message
            );
        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'error',
                'error_messages' => [$e->getMessage()],
                'finished_at' => now(),
            ]);

            $integration->markSyncError($e->getMessage());

            Log::error("Integration sync error: {$e->getMessage()}", [
                'integration_id' => $integration->id,
            ]);

            return back()->with('error', 'Erro na sincronização: ' . $e->getMessage());
        }
    }

    public function syncLogs(int $integration)
    {
        $tenant = tenant();

        $integration = TenantIntegration::where('tenant_id', $tenant->id)
            ->findOrFail($integration);

        $logs = IntegrationSyncLog::where('integration_id', $integration->id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($logs);
    }

    protected function maskSensitiveConfig(array $config): array
    {
        $sensitiveKeys = ['db_password', 'auth_password', 'auth_token', 'webhook_secret', 'api_key'];

        foreach ($sensitiveKeys as $key) {
            if (isset($config[$key]) && $config[$key]) {
                $config[$key] = '********';
            }
        }

        return $config;
    }
}
