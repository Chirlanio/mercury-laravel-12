<?php

namespace App\Http\Middleware;

use App\Services\CentralRoleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function __construct(
        protected CentralRoleResolver $resolver,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $role  The minimum role slug required
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $userLevel = $this->resolver->getHierarchyLevel($user->role->value);
        $requiredLevel = $this->resolver->getHierarchyLevel($role);

        if ($userLevel < $requiredLevel) {
            abort(403, 'Acesso negado. Você não tem permissão para acessar esta área.');
        }

        return $next($request);
    }
}
