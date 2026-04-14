<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Helpdesk\ProcessInboundEmailJob;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public webhook receiver for Postmark Inbound.
 *
 * URL: POST /api/webhooks/helpdesk/email/{tenant}
 *
 * Auth: a shared token provided as either:
 *   - HTTP Basic Auth username == config('services.postmark_inbound.webhook_token'), OR
 *   - `x-mercury-webhook-token` header (mirrors the WhatsApp webhook), OR
 *   - `?token=...` query string (fallback when upstream strips headers).
 *
 * Postmark doesn't HMAC-sign the payload by default; the recommended
 * practice is to protect the URL with HTTP Basic Auth (see
 * https://postmarkapp.com/support/article/1037-how-can-i-secure-the-webhooks-for-inbound-processing).
 * We accept either Basic Auth OR the header token so either flavor works.
 *
 * Always responds 200 OK after queuing so Postmark doesn't retry —
 * the job handles errors and Horizon captures failures.
 */
class PostmarkInboundWebhookController extends Controller
{
    public function handle(Request $request, string $tenant)
    {
        $expectedToken = config('services.postmark_inbound.webhook_token');

        if ($expectedToken) {
            $provided = $this->extractToken($request);
            if (! hash_equals((string) $expectedToken, (string) $provided)) {
                Log::warning('Postmark inbound webhook: invalid auth token', [
                    'tenant' => $tenant,
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        if (! Tenant::query()->where('id', $tenant)->exists()) {
            Log::warning('Postmark inbound webhook: unknown tenant', ['tenant' => $tenant]);

            return response()->json(['error' => 'Unknown tenant'], 404);
        }

        $payload = $request->all();

        if (empty($payload) || ! is_array($payload)) {
            return response()->json(['error' => 'Empty payload'], 422);
        }

        ProcessInboundEmailJob::dispatch($tenant, $payload);

        return response()->json(['status' => 'queued'], 202);
    }

    /**
     * Read the shared secret from Basic Auth user, a custom header, or the
     * query string — whichever got through the upstream.
     */
    protected function extractToken(Request $request): ?string
    {
        $basicUser = $request->getUser();
        if ($basicUser) {
            return $basicUser;
        }

        $header = $request->header('x-mercury-webhook-token');
        if ($header) {
            return (string) $header;
        }

        $query = $request->query('token');
        if ($query) {
            return (string) $query;
        }

        return null;
    }
}
