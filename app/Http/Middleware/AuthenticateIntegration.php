<?php

namespace App\Http\Middleware;

use App\Models\TenantIntegration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Integration-Key') ?? $request->query('api_key');

        if (! $apiKey) {
            return response()->json(['error' => 'API key required'], 401);
        }

        $integration = TenantIntegration::where('id', $request->route('integration'))
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return response()->json(['error' => 'Integration not found or inactive'], 404);
        }

        $config = $integration->config;
        if (! $config || ($config['api_key'] ?? null) !== $apiKey) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        // Initialize tenancy for this integration's tenant
        tenancy()->initialize($integration->tenant);

        $request->merge(['integration' => $integration]);

        return $next($request);
    }
}
