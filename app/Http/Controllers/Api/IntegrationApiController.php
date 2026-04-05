<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSyncLog;
use App\Models\TenantIntegration;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationApiController extends Controller
{
    public function __construct(
        protected IntegrationManager $manager,
    ) {}

    public function status(Request $request, int $integration): JsonResponse
    {
        $integration = $request->get('integration');

        $lastSync = IntegrationSyncLog::where('integration_id', $integration->id)
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'id' => $integration->id,
            'name' => $integration->name,
            'is_active' => $integration->is_active,
            'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
            'last_sync_status' => $integration->last_sync_status,
            'last_sync' => $lastSync ? [
                'status' => $lastSync->status,
                'records_processed' => $lastSync->records_processed,
                'records_created' => $lastSync->records_created,
                'started_at' => $lastSync->started_at->toIso8601String(),
                'finished_at' => $lastSync->finished_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function triggerSync(Request $request, int $integration): JsonResponse
    {
        $integration = $request->get('integration');

        $driver = $this->manager->resolve($integration);
        $options = $request->only(['date_from', 'date_to', 'resource']);

        $syncLog = IntegrationSyncLog::create([
            'integration_id' => $integration->id,
            'tenant_id' => $integration->tenant_id,
            'direction' => 'pull',
            'status' => 'running',
            'started_at' => now(),
            'triggered_by' => 'api',
        ]);

        try {
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

            return response()->json([
                'status' => empty($result['errors']) ? 'success' : 'partial',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'error',
                'error_messages' => [$e->getMessage()],
                'finished_at' => now(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getData(Request $request, int $integration, string $resource): JsonResponse
    {
        $integration = $request->get('integration');

        $driver = $this->manager->resolve($integration);
        $resources = $driver->getAvailableResources();

        if (! isset($resources[$resource])) {
            return response()->json(['error' => "Resource '{$resource}' not available"], 404);
        }

        $result = $driver->pull(array_merge(
            $request->only(['date_from', 'date_to', 'page', 'per_page']),
            ['resource' => $resource]
        ));

        return response()->json($result);
    }

    public function pushData(Request $request, int $integration, string $resource): JsonResponse
    {
        $integration = $request->get('integration');

        $driver = $this->manager->resolve($integration);

        $result = $driver->push([
            'resource' => $resource,
            'data' => $request->input('data', []),
        ]);

        return response()->json($result);
    }
}
