<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralDomain
{
    /**
     * Only allow requests from central domains.
     * Returns 404 if accessed from a tenant subdomain.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $centralDomains = config('tenancy.central_domains', []);
        $hostname = $request->getHost();

        if (! in_array($hostname, $centralDomains)) {
            abort(404);
        }

        return $next($request);
    }
}
