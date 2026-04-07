<?php

/*
|--------------------------------------------------------------------------
| Central Routes
|--------------------------------------------------------------------------
|
| Routes for the central domain. Uses Route::domain() to ensure
| these routes only match on central domains (not tenant subdomains).
|
*/

use App\Http\Controllers\Central\Auth\LoginController;
use App\Http\Controllers\Central\DashboardController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\Central\PlanController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Register central routes for each configured central domain
// This prevents tenant routes from overriding them
// Skip domain-scoped central routes in testing (tests access routes directly without subdomain)
if (app()->environment('testing')) {
    return;
}

$centralDomains = config('tenancy.central_domains', ['localhost', '127.0.0.1']);

foreach ($centralDomains as $domain) {
    Route::domain($domain)->name($domain === $centralDomains[0] ? '' : "cd_{$domain}.")
        ->group(function () {

        // Public
        Route::get('/', function () {
            if (auth()->guard('central')->check()) {
                return redirect('/admin');
            }
            return Inertia::render('Central/Welcome');
        })->name('central.welcome');

        Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');

        // Central auth
        Route::middleware('guest:central')->group(function () {
            Route::get('/login', [LoginController::class, 'showLoginForm'])->name('central.login');
            Route::post('/login', [LoginController::class, 'login'])
                ->middleware('throttle:central-login')
                ->name('central.login.submit');
        });

        Route::post('/logout', [LoginController::class, 'logout'])
            ->middleware('auth:central')
            ->name('central.logout');

        // Admin panel
        Route::middleware('auth:central')->prefix('admin')->name('central.')->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
            Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
            Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
            Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
            Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
            Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
            Route::post('/tenants/{tenant}/reactivate', [TenantController::class, 'reactivate'])->name('tenants.reactivate');

            Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
            Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
            Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
            Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
        });
    });
}
