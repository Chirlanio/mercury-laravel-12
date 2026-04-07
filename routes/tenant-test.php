<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tenant Routes for Testing
|--------------------------------------------------------------------------
|
| Same route definitions as tenant.php but without tenancy middleware,
| allowing feature tests to access routes without subdomain setup.
| Only loaded when APP_ENV=testing (via TenancyServiceProvider).
|
*/

require __DIR__.'/tenant-routes.php';

require __DIR__.'/auth.php';
