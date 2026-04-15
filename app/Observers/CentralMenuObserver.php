<?php

namespace App\Observers;

use App\Models\CentralMenu;
use App\Services\CentralMenuResolver;

/**
 * Invalidates the sidebar menu cache whenever a CentralMenu row changes.
 *
 * The cache is file-based and keyed by (role, tenant), so we clear every
 * combination — a single saved/deleted event can affect any tenant whose
 * plan references the mutated menu. Cheap compared to the risk of stale
 * sidebars for up to 5 minutes after an admin edit.
 */
class CentralMenuObserver
{
    public function __construct(protected CentralMenuResolver $resolver)
    {
    }

    public function saved(CentralMenu $menu): void
    {
        $this->resolver->clearCache();
    }

    public function deleted(CentralMenu $menu): void
    {
        $this->resolver->clearCache();
    }
}
