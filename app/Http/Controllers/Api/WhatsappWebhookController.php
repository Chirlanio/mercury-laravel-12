<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Helpdesk\ProcessIncomingWhatsappMessageJob;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public webhook receiver for Evolution API (WhatsApp gateway).
 *
 * URL: POST /api/webhooks/whatsapp/{tenant}
 *   - tenant = the tenant id (matches App\Models\Tenant::$id)
 *   - Header: x-mercury-webhook-token: shared secret from EVOLUTION_WEBHOOK_TOKEN
 *
 * Always responds 200 OK after queuing (or rejecting malformed payloads) so
 * Evolution doesn't retry — the job takes over for processing and error
 * handling lives in the queue.
 */
class WhatsappWebhookController extends Controller
{
    public function handle(Request $request, string $tenant)
    {
        $expectedToken = config('services.evolution.webhook_token');

        if ($expectedToken) {
            // Accept the token either as a header (preferred — invisible to
            // anyone who snoops the URL) or as a query string parameter
            // (fallback when the Evolution Manager UI drops custom headers).
            $provided = (string) ($request->header('x-mercury-webhook-token') ?? $request->query('token') ?? '');

            if (! hash_equals((string) $expectedToken, $provided)) {
                Log::warning('WhatsApp webhook: invalid auth token', [
                    'tenant' => $tenant,
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        if (! Tenant::query()->where('id', $tenant)->exists()) {
            Log::warning('WhatsApp webhook: unknown tenant', ['tenant' => $tenant]);

            return response()->json(['error' => 'Unknown tenant'], 404);
        }

        $payload = $request->all();

        ProcessIncomingWhatsappMessageJob::dispatch($tenant, $payload);

        return response()->json(['status' => 'queued'], 202);
    }
}
