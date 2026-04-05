<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSyncLog;
use App\Models\TenantIntegration;
use App\Services\Integrations\Drivers\WebhookDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IntegrationWebhookController extends Controller
{
    public function receive(Request $request, int $integration): JsonResponse
    {
        $integration = TenantIntegration::where('is_active', true)
            ->where('driver', 'webhook')
            ->findOrFail($integration);

        $config = $integration->config;

        // Validate webhook secret
        $secret = $request->header('X-Webhook-Secret') ?? $request->input('secret');
        if (! $secret || $secret !== ($config['webhook_secret'] ?? '')) {
            return response()->json(['error' => 'Invalid webhook secret'], 401);
        }

        // Validate IP if configured
        $allowedIps = $config['allowed_ips'] ?? null;
        if ($allowedIps) {
            $ips = array_map('trim', explode(',', $allowedIps));
            if (! in_array($request->ip(), $ips)) {
                return response()->json(['error' => 'IP not allowed'], 403);
            }
        }

        // Initialize tenant context
        tenancy()->initialize($integration->tenant);

        $syncLog = IntegrationSyncLog::create([
            'integration_id' => $integration->id,
            'tenant_id' => $integration->tenant_id,
            'direction' => 'push',
            'status' => 'running',
            'started_at' => now(),
            'triggered_by' => 'webhook:' . $request->ip(),
        ]);

        try {
            $payload = $request->all();

            $driver = new WebhookDriver();
            $driver->initialize($integration);
            $result = $driver->processWebhook($payload);

            $syncLog->update([
                'status' => 'success',
                'records_processed' => $result['records'] ?? 0,
                'finished_at' => now(),
            ]);

            $integration->markSyncSuccess("Webhook recebido: {$result['records']} registros.");

            return response()->json([
                'status' => 'accepted',
                'records' => $result['records'] ?? 0,
            ]);
        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'error',
                'error_messages' => [$e->getMessage()],
                'finished_at' => now(),
            ]);

            Log::error("Webhook processing error: {$e->getMessage()}", [
                'integration_id' => $integration->id,
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }
}
