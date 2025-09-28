<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Não autenticado.');
        }

        // Verificar se o usuário tem pelo menos uma das permissões
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($user->role->hasPermissionTo($permission)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Você não tem permissão para acessar este recurso.'
                ], 403);
            }

            abort(403, 'Você não tem permissão para acessar este recurso.');
        }

        return $next($request);
    }
}
