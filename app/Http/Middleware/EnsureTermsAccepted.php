<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    /**
     * Current terms version. Bump this when terms are updated
     * to require users to re-accept.
     */
    public const TERMS_VERSION = '1.0';

    /**
     * Routes that should be accessible without accepting terms.
     */
    protected array $except = [
        'terms.show',
        'terms.accept',
        'privacy.show',
        'logout',
        'central.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Skip for whitelisted routes
        if ($request->routeIs(...$this->except)) {
            return $next($request);
        }

        // Check if user has accepted current terms version
        if (! $user->terms_accepted_at || $user->terms_version !== self::TERMS_VERSION) {
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return inertia()->location(route('terms.show'));
            }

            return redirect()->route('terms.show');
        }

        return $next($request);
    }
}
