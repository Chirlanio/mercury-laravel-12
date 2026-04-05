<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant) {
            return $next($request);
        }

        // Check if tenant is active
        if (! $tenant->is_active) {
            auth()->logout();
            $request->session()->invalidate();

            abort(403, 'Esta conta esta suspensa. Entre em contato com o suporte.');
        }

        // Check if trial has expired without a plan
        if ($tenant->isExpired()) {
            auth()->logout();
            $request->session()->invalidate();

            abort(403, 'Seu periodo de avaliacao expirou. Contrate um plano para continuar.');
        }

        return $next($request);
    }
}
