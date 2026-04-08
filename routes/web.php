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
use App\Http\Controllers\Central\ModuleController;
use App\Http\Controllers\Central\InvoiceController;
use App\Http\Controllers\Central\ManualController;
use App\Http\Controllers\Central\NavigationController;
use App\Http\Controllers\Central\RolePermissionController;
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
            Route::put('/tenants/{tenant}/allowed-roles', [TenantController::class, 'updateAllowedRoles'])->name('tenants.updateAllowedRoles');
            Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
            Route::post('/tenants/{tenant}/reactivate', [TenantController::class, 'reactivate'])->name('tenants.reactivate');

            Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
            Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
            Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
            Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

            Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
            Route::post('/modules', [ModuleController::class, 'store'])->name('modules.store');
            Route::put('/modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
            Route::delete('/modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');

            // Navigation management
            Route::get('/navigation', [NavigationController::class, 'index'])->name('navigation.index');
            Route::post('/navigation/menus', [NavigationController::class, 'storeMenu'])->name('navigation.menus.store');
            Route::put('/navigation/menus/{menu}', [NavigationController::class, 'updateMenu'])->name('navigation.menus.update');
            Route::delete('/navigation/menus/{menu}', [NavigationController::class, 'destroyMenu'])->name('navigation.menus.destroy');
            Route::post('/navigation/pages', [NavigationController::class, 'storePage'])->name('navigation.pages.store');
            Route::put('/navigation/pages/{page}', [NavigationController::class, 'updatePage'])->name('navigation.pages.update');
            Route::delete('/navigation/pages/{page}', [NavigationController::class, 'destroyPage'])->name('navigation.pages.destroy');
            Route::post('/navigation/page-groups', [NavigationController::class, 'storePageGroup'])->name('navigation.pageGroups.store');
            Route::put('/navigation/page-groups/{pageGroup}', [NavigationController::class, 'updatePageGroup'])->name('navigation.pageGroups.update');
            Route::delete('/navigation/page-groups/{pageGroup}', [NavigationController::class, 'destroyPageGroup'])->name('navigation.pageGroups.destroy');
            Route::put('/navigation/defaults', [NavigationController::class, 'updateDefaults'])->name('navigation.defaults.update');

            // Roles & Permissions management
            Route::get('/roles', [RolePermissionController::class, 'index'])->name('roles.index');
            Route::post('/roles', [RolePermissionController::class, 'storeRole'])->name('roles.store');
            Route::put('/roles/{role}', [RolePermissionController::class, 'updateRole'])->name('roles.update');
            Route::delete('/roles/{role}', [RolePermissionController::class, 'destroyRole'])->name('roles.destroy');
            Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'updateRolePermissions'])->name('roles.permissions.update');

            // Invoices / Billing
            Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
            Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
            Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid'])->name('invoices.markAsPaid');
            Route::post('/invoices/{invoice}/mark-overdue', [InvoiceController::class, 'markAsOverdue'])->name('invoices.markAsOverdue');
            Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
            Route::post('/invoices/{invoice}/charge-asaas', [InvoiceController::class, 'chargeOnAsaas'])->name('invoices.chargeAsaas');
            Route::get('/invoices/{invoice}/pix-qrcode', [InvoiceController::class, 'getPixQrCode'])->name('invoices.pixQrCode');
            Route::post('/invoices/generate-for-tenant', [InvoiceController::class, 'generateForTenant'])->name('invoices.generateForTenant');
            Route::post('/invoices/generate-bulk', [InvoiceController::class, 'generateBulk'])->name('invoices.generateBulk');

            Route::get('/manual', [ManualController::class, 'download'])->name('manual.download');
        });
    });
}
