<?php

namespace App\Http\Middleware;

use App\Services\PlanLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimit
{
    public function __construct(
        protected PlanLimitService $limits,
    ) {}

    /**
     * Check plan limits before resource creation.
     * Usage: middleware('plan.limit:users') or middleware('plan.limit:stores')
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $canCreate = match ($resource) {
            'users' => $this->limits->canCreateUser(),
            'stores' => $this->limits->canCreateStore(),
            default => true,
        };

        if (! $canCreate) {
            $message = $this->limits->getLimitMessage($resource);

            if ($request->wantsJson()) {
                return response()->json(['error' => $message], 403);
            }

            return back()->with('error', $message);
        }

        return $next($request);
    }
}
