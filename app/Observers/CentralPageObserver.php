<?php

namespace App\Observers;

use App\Models\CentralPage;
use App\Services\CentralMenuResolver;

/**
 * Invalidates sidebar menu cache when a CentralPage is saved or deleted.
 * Covers page rename, route change, is_active toggle, and module reassignment
 * — all of which affect what tenants see in their sidebar.
 */
class CentralPageObserver
{
    public function __construct(protected CentralMenuResolver $resolver)
    {
    }

    public function saved(CentralPage $page): void
    {
        $this->resolver->clearCache();
    }

    public function deleted(CentralPage $page): void
    {
        $this->resolver->clearCache();
    }
}
