<?php

namespace App\Http\Middleware;

use App\Services\CentralRoleResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $currentTenant = tenant();
        $isCentral = ! $currentTenant;

        // Central context: use central guard
        if ($isCentral) {
            return $this->shareCentralProps($request);
        }

        // Tenant context: use web guard (tenant users)
        return $this->shareTenantProps($request, $currentTenant);
    }

    protected function shareCentralProps(Request $request): array
    {
        $user = Auth::guard('central')->user();

        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'isCentral' => true,
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ] : null,
                'permissions' => [],
            ],
            'tenant' => null,
            'flash' => $this->flashProps($request),
        ];
    }

    protected function shareTenantProps(Request $request, $currentTenant): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'isCentral' => false,
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'store_id' => $user->store_id,
                    'avatar' => $user->avatar,
                    'avatar_url' => $user->avatar_url,
                    'has_custom_avatar' => $user->hasCustomAvatar(),
                ] : null,
                'permissions' => $user && $user->role ? app(CentralRoleResolver::class)->getPermissionsForRole($user->role->value) : [],
            ],
            'tenant' => [
                'id' => $currentTenant->id,
                'name' => $currentTenant->name,
                'slug' => $currentTenant->slug,
                'plan' => $currentTenant->plan ? [
                    'name' => $currentTenant->plan->name,
                    'slug' => $currentTenant->plan->slug,
                ] : null,
                'modules' => $currentTenant->activeModules()->pluck('module_slug'),
                'settings' => $currentTenant->settings ?? [],
            ],
            'flash' => $this->flashProps($request),
        ];
    }

    protected function flashProps(Request $request): array
    {
        return [
            'success' => fn () => $request->session()->get('success'),
            'error' => fn () => $request->session()->get('error'),
            'warning' => fn () => $request->session()->get('warning'),
            'info' => fn () => $request->session()->get('info'),
        ];
    }
}
