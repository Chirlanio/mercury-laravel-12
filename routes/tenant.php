<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| These routes are loaded for tenant domains (subdomains).
| They use the tenant middleware stack to initialize tenancy.
| Route definitions are in tenant-routes.php (shared with test config).
|
*/

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
    'tenant.active',
    'terms.accepted',
])->group(function () {

    require __DIR__.'/tenant-routes.php';

    // Auth routes for tenant
    require __DIR__.'/auth.php';
});
