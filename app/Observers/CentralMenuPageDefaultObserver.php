<?php

namespace App\Observers;

use App\Models\CentralMenuPageDefault;
use App\Services\CentralMenuResolver;

/**
 * Invalidates sidebar menu cache when a menu↔page↔role assignment changes.
 * This is the hottest path — every permission toggle, reorder, or new
 * assignment flows through here and needs the tenant sidebar refreshed.
 */
class CentralMenuPageDefaultObserver
{
    public function __construct(protected CentralMenuResolver $resolver)
    {
    }

    public function saved(CentralMenuPageDefault $default): void
    {
        // Targeted invalidation — we know the exact role this row affects,
        // so we can skip clearing caches for unrelated roles.
        $this->resolver->clearCache($default->role_slug);
    }

    public function deleted(CentralMenuPageDefault $default): void
    {
        $this->resolver->clearCache($default->role_slug);
    }
}
