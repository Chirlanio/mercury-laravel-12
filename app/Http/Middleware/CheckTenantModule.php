<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantModule
{
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        $tenant = tenant();

        // Sem tenant context (testes, desenvolvimento local sem subdomínio)
        if (! $tenant) {
            return $next($request);
        }

        if (! $tenant->hasModule($moduleSlug)) {
            abort(403, 'Módulo não disponível no seu plano atual.');
        }

        return $next($request);
    }
}
