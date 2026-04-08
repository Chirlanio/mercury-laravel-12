<?php

// Shared route definitions for tenant context.
// Included by both tenant.php (with tenancy middleware) and tenant-test.php (without).

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StoreGoalController;
use App\Http\Controllers\Config\PercentageAwardController as ConfigPercentageAwardController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AccessLevelController;
use App\Http\Controllers\AccessLevelPageController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\WorkShiftController;
use App\Http\Controllers\WorkScheduleController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ColorThemeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PageGroupController;
use App\Http\Controllers\UserSessionController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\OrderPaymentController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\Admin\EmailSettingsController;
use App\Http\Controllers\Config\PositionController as ConfigPositionController;
use App\Http\Controllers\Config\PositionLevelController as ConfigPositionLevelController;
use App\Http\Controllers\Config\SectorController as ConfigSectorController;
use App\Http\Controllers\Config\GenderController as ConfigGenderController;
use App\Http\Controllers\Config\EducationLevelController as ConfigEducationLevelController;
use App\Http\Controllers\Config\EmployeeStatusController as ConfigEmployeeStatusController;
use App\Http\Controllers\Config\EmployeeEventTypeController as ConfigEmployeeEventTypeController;
use App\Http\Controllers\Config\TypeMovimentController as ConfigTypeMovimentController;
use App\Http\Controllers\Config\EmploymentRelationshipController as ConfigEmploymentRelationshipController;
use App\Http\Controllers\Config\ManagerController as ConfigManagerController;
use App\Http\Controllers\Config\NetworkController as ConfigNetworkController;
use App\Http\Controllers\Config\StatusController as ConfigStatusController;
use App\Http\Controllers\Config\PageStatusController as ConfigPageStatusController;
use App\Http\Controllers\Config\BankController as ConfigBankController;
use App\Http\Controllers\Config\CostCenterController as ConfigCostCenterController;
use App\Http\Controllers\Config\PaymentTypeController as ConfigPaymentTypeController;
use App\Http\Controllers\Config\DriverController as ConfigDriverController;
use App\Http\Controllers\Config\StockAdjustmentStatusController as ConfigStockAdjustmentStatusController;
use App\Http\Controllers\MedicalCertificateController;
use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\OvertimeRecordController;
use App\Http\Controllers\Config\TransferStatusController as ConfigTransferStatusController;
use App\Http\Controllers\Config\OrderPaymentStatusController as ConfigOrderPaymentStatusController;
use App\Http\Controllers\Config\ManagementReasonController as ConfigManagementReasonController;
use App\Http\Controllers\Config\ProductBrandController as ConfigProductBrandController;
use App\Http\Controllers\Config\ProductCategoryController as ConfigProductCategoryController;
use App\Http\Controllers\Config\ProductCollectionController as ConfigProductCollectionController;
use App\Http\Controllers\Config\ProductSubcollectionController as ConfigProductSubcollectionController;
use App\Http\Controllers\Config\ProductColorController as ConfigProductColorController;
use App\Http\Controllers\Config\ProductMaterialController as ConfigProductMaterialController;
use App\Http\Controllers\Config\ProductSizeController as ConfigProductSizeController;
use App\Http\Controllers\Config\ProductArticleComplementController as ConfigProductArticleComplementController;
use App\Http\Controllers\LgpdController;
use App\Http\Controllers\MovementController;
use App\Enums\Permission;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

    // ==========================================
    // Root & Dashboard
    // ==========================================
    Route::get('/', function () {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }
        return redirect()->route('login');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware(['auth', 'verified', 'permission:' . Permission::ACCESS_DASHBOARD->value])
        ->name('dashboard');

    // ==========================================
    // LGPD / Terms & Privacy
    // ==========================================
    Route::middleware('auth')->group(function () {
        Route::get('/terms', [LgpdController::class, 'showTerms'])->name('terms.show');
        Route::post('/terms/accept', [LgpdController::class, 'acceptTerms'])->name('terms.accept');
        Route::get('/privacy', [LgpdController::class, 'showPrivacy'])->name('privacy.show');
        Route::get('/lgpd/export', [LgpdController::class, 'exportMyData'])->name('lgpd.export');
        Route::post('/lgpd/delete', [LgpdController::class, 'requestDeletion'])->name('lgpd.delete');
    });

    // ==========================================
    // Profile
    // ==========================================
    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])
            ->middleware('permission:' . Permission::VIEW_OWN_PROFILE->value)
            ->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])
            ->middleware('permission:' . Permission::EDIT_OWN_PROFILE->value)
            ->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])
            ->middleware('permission:' . Permission::EDIT_OWN_PROFILE->value)
            ->name('profile.destroy');
    });

    // ==========================================
    // Admin & Support Panels
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::ACCESS_ADMIN_PANEL->value])->group(function () {
        Route::get('/admin', function () {
            return Inertia::render('Admin');
        })->name('admin');

        Route::middleware('permission:' . Permission::MANAGE_SETTINGS->value)->group(function () {
            Route::get('/admin/email-settings', [EmailSettingsController::class, 'index'])->name('admin.email-settings');
        });
    });

    Route::middleware(['auth', 'permission:' . Permission::ACCESS_SUPPORT_PANEL->value])->group(function () {
        Route::get('/support', function () {
            return Inertia::render('Support');
        })->name('support');
    });

    // ==========================================
    // User Management
    // ==========================================
    Route::middleware('auth')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])
            ->middleware('permission:' . Permission::VIEW_USERS->value)
            ->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])
            ->middleware('permission:' . Permission::CREATE_USERS->value)
            ->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware(['permission:' . Permission::CREATE_USERS->value, 'plan.limit:users'])
            ->name('users.store');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])
            ->middleware('permission:' . Permission::VIEW_USERS->value . ',' . Permission::VIEW_ANY_PROFILE->value)
            ->name('users.show');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])
            ->middleware('permission:' . Permission::EDIT_USERS->value . ',' . Permission::EDIT_ANY_PROFILE->value)
            ->name('users.edit');
        Route::match(['put', 'post'], '/users/{user}', [UserManagementController::class, 'update'])
            ->middleware('permission:' . Permission::EDIT_USERS->value . ',' . Permission::EDIT_ANY_PROFILE->value)
            ->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
            ->middleware('permission:' . Permission::DELETE_USERS->value)
            ->name('users.destroy');
        Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole'])
            ->middleware('permission:' . Permission::MANAGE_USER_ROLES->value)
            ->name('users.updateRole');
        Route::delete('/users/{user}/avatar', [UserManagementController::class, 'removeAvatar'])
            ->middleware('permission:' . Permission::EDIT_USERS->value . ',' . Permission::EDIT_ANY_PROFILE->value)
            ->name('users.removeAvatar');
    });

    // ==========================================
    // Activity Logs
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_ACTIVITY_LOGS->value])->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/{log}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
        Route::post('/activity-logs/export', [ActivityLogController::class, 'export'])
            ->middleware('permission:' . Permission::EXPORT_ACTIVITY_LOGS->value)
            ->name('activity-logs.export');
        Route::delete('/activity-logs/cleanup', [ActivityLogController::class, 'cleanup'])
            ->middleware('permission:' . Permission::MANAGE_SYSTEM_SETTINGS->value)
            ->name('activity-logs.cleanup');
    });

    // ==========================================
    // Menus
    // ==========================================
    Route::middleware('auth')->get('/api/menus/sidebar', [MenuController::class, 'getSidebarMenus'])->name('menus.sidebar');
    Route::middleware('auth')->get('/api/menus/dynamic-sidebar', [MenuController::class, 'getDynamicSidebarMenus'])->name('menus.dynamic-sidebar');

    Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
        Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
        Route::get('/menus/{menu}', [MenuController::class, 'show'])->name('menus.show');
        Route::middleware('permission:' . Permission::MANAGE_SYSTEM_SETTINGS->value)->group(function () {
            Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');
            Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');
            Route::post('/menus/{menu}/activate', [MenuController::class, 'activate'])->name('menus.activate');
            Route::post('/menus/{menu}/deactivate', [MenuController::class, 'deactivate'])->name('menus.deactivate');
            Route::post('/menus/{menu}/move-up', [MenuController::class, 'moveUp'])->name('menus.moveUp');
            Route::post('/menus/{menu}/move-down', [MenuController::class, 'moveDown'])->name('menus.moveDown');
            Route::post('/menus/reorder', [MenuController::class, 'reorder'])->name('menus.reorder');
            Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
        });
    });

    // ==========================================
    // Pages
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
        Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
        Route::post('/pages', [PageController::class, 'store'])->name('pages.store');
        Route::get('/pages/{page}', [PageController::class, 'show'])->name('pages.show');
        Route::patch('/pages/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::middleware('permission:' . Permission::MANAGE_SYSTEM_SETTINGS->value)->group(function () {
            Route::post('/pages/{page}/activate', [PageController::class, 'activate'])->name('pages.activate');
            Route::post('/pages/{page}/deactivate', [PageController::class, 'deactivate'])->name('pages.deactivate');
            Route::post('/pages/{page}/make-public', [PageController::class, 'makePublic'])->name('pages.makePublic');
            Route::post('/pages/{page}/make-private', [PageController::class, 'makePrivate'])->name('pages.makePrivate');
            Route::delete('/pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
            Route::post('/pages/sync-permissions', [PageController::class, 'syncPermissions'])->name('pages.syncPermissions');
        });
    });

    // ==========================================
    // Page Groups
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
        Route::get('/page-groups', [PageGroupController::class, 'index'])->name('page-groups.index');
        Route::get('/page-groups/{pageGroup}', [PageGroupController::class, 'show'])->name('page-groups.show');
        Route::middleware('permission:' . Permission::MANAGE_SYSTEM_SETTINGS->value)->group(function () {
            Route::post('/page-groups', [PageGroupController::class, 'store'])->name('page-groups.store');
            Route::put('/page-groups/{pageGroup}', [PageGroupController::class, 'update'])->name('page-groups.update');
            Route::delete('/page-groups/{pageGroup}', [PageGroupController::class, 'destroy'])->name('page-groups.destroy');
        });
    });

    // ==========================================
    // Access Levels
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
        Route::get('/access-levels', [AccessLevelController::class, 'index'])->name('access-levels.index');
        Route::get('/access-levels/{accessLevel}/permissions', [AccessLevelController::class, 'getPermissions'])
            ->name('access-levels.permissions');
        Route::post('/access-levels/{accessLevel}/permissions', [AccessLevelController::class, 'updatePermissions'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)
            ->name('access-levels.permissions.update');
        Route::get('/access-levels/{accessLevel}', [AccessLevelController::class, 'show'])->name('access-levels.show');
        Route::post('/access-levels', [AccessLevelController::class, 'store'])
            ->middleware('permission:' . Permission::CREATE_USERS->value)
            ->name('access-levels.store');
        Route::put('/access-levels/{accessLevel}', [AccessLevelController::class, 'update'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)
            ->name('access-levels.update');
        Route::delete('/access-levels/{accessLevel}', [AccessLevelController::class, 'destroy'])
            ->middleware('permission:' . Permission::DELETE_USERS->value)
            ->name('access-levels.destroy');
        Route::get('/access-levels/{accessLevel}/menus/{menu}/pages', [AccessLevelPageController::class, 'manage'])
            ->name('access-levels.menus.pages.manage');
        Route::get('/access-levels/{accessLevel}/menus/{menu}/pages/json', [AccessLevelPageController::class, 'getPages'])
            ->name('access-levels.menus.pages.get');
        Route::post('/access-levels/{accessLevel}/menus/{menu}/pages', [AccessLevelPageController::class, 'updatePermissions'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)
            ->name('access-levels.menus.pages.update');
    });

    // ==========================================
    // Employees
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
        Route::get('/employees/export', [EmployeeController::class, 'export'])->name('employees.export');
        Route::get('/employees/list-json', [EmployeeController::class, 'listJson'])->name('employees.list-json');
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])
            ->middleware('permission:' . Permission::CREATE_USERS->value)
            ->name('employees.store');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)
            ->name('employees.edit');
        Route::match(['put', 'post'], '/employees/{employee}', [EmployeeController::class, 'update'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)
            ->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
            ->middleware('permission:' . Permission::DELETE_USERS->value)
            ->name('employees.destroy');
        Route::get('/employees/{employee}/history', [EmployeeController::class, 'history'])->name('employees.history');
        Route::post('/employees/{employee}/contracts', [EmployeeController::class, 'storeContract'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('employees.contracts.store');
        Route::put('/employees/{employee}/contracts/{contract}', [EmployeeController::class, 'updateContract'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('employees.contracts.update');
        Route::delete('/employees/{employee}/contracts/{contract}', [EmployeeController::class, 'destroyContract'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('employees.contracts.destroy');
        Route::post('/employees/{employee}/contracts/{contract}/reactivate', [EmployeeController::class, 'reactivateContract'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('employees.contracts.reactivate');
        Route::get('/employees/{employee}/events', [EmployeeController::class, 'getEvents'])->name('employees.events.index');
        Route::post('/employees/{employee}/events', [EmployeeController::class, 'storeEvent'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('employees.events.store');
        Route::delete('/employees/{employee}/events/{event}', [EmployeeController::class, 'destroyEvent'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('employees.events.destroy');
        Route::get('/employees/{employee}/events/export', [EmployeeController::class, 'exportEvents'])->name('employees.events.export');
        Route::get('/employees/{employee}/report', [EmployeeController::class, 'generateReport'])->name('employees.report');
        Route::get('/employees/events/export', [EmployeeController::class, 'exportAllEvents'])->name('employees.all-events.export');
    });

    // ==========================================
    // Work Shifts
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
        Route::get('/work-shifts', [WorkShiftController::class, 'index'])->name('work-shifts.index');
        Route::get('/work-shifts/export', [WorkShiftController::class, 'export'])->name('work-shifts.export');
        Route::get('/work-shifts/print-summary', [WorkShiftController::class, 'printSummary'])->name('work-shifts.print-summary');
        Route::post('/work-shifts', [WorkShiftController::class, 'store'])
            ->middleware('permission:' . Permission::CREATE_USERS->value)->name('work-shifts.store');
        Route::get('/work-shifts/{workShift}', [WorkShiftController::class, 'show'])->name('work-shifts.show');
        Route::get('/work-shifts/{workShift}/edit', [WorkShiftController::class, 'edit'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('work-shifts.edit');
        Route::match(['put', 'post'], '/work-shifts/{workShift}', [WorkShiftController::class, 'update'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('work-shifts.update');
        Route::delete('/work-shifts/{workShift}', [WorkShiftController::class, 'destroy'])
            ->middleware('permission:' . Permission::DELETE_USERS->value)->name('work-shifts.destroy');
    });

    // ==========================================
    // Work Schedules
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
        Route::get('/work-schedules', [WorkScheduleController::class, 'index'])->name('work-schedules.index');
        Route::get('/work-schedules/list-json', [WorkScheduleController::class, 'listJson'])->name('work-schedules.list-json');
        Route::get('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'show'])->name('work-schedules.show');
        Route::get('/work-schedules/{workSchedule}/edit', [WorkScheduleController::class, 'edit'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('work-schedules.edit');
        Route::get('/work-schedules/{workSchedule}/employees', [WorkScheduleController::class, 'getEmployees'])->name('work-schedules.employees');
        Route::post('/work-schedules', [WorkScheduleController::class, 'store'])
            ->middleware('permission:' . Permission::CREATE_USERS->value)->name('work-schedules.store');
        Route::post('/work-schedules/{workSchedule}/duplicate', [WorkScheduleController::class, 'duplicate'])
            ->middleware('permission:' . Permission::CREATE_USERS->value)->name('work-schedules.duplicate');
        Route::post('/work-schedules/{workSchedule}/employees', [WorkScheduleController::class, 'assignEmployee'])
            ->middleware('permission:' . Permission::CREATE_USERS->value)->name('work-schedules.assign-employee');
        Route::match(['put', 'post'], '/work-schedules/{workSchedule}/update', [WorkScheduleController::class, 'update'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('work-schedules.update');
        Route::delete('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'destroy'])
            ->middleware('permission:' . Permission::DELETE_USERS->value)->name('work-schedules.destroy');
        Route::delete('/work-schedules/{workSchedule}/employees/{assignment}', [WorkScheduleController::class, 'unassignEmployee'])
            ->middleware('permission:' . Permission::DELETE_USERS->value)->name('work-schedules.unassign-employee');
        Route::post('/employee-schedules/{assignment}/overrides', [WorkScheduleController::class, 'storeOverride'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('employee-schedules.overrides.store');
        Route::delete('/employee-schedules/{assignment}/overrides/{override}', [WorkScheduleController::class, 'destroyOverride'])
            ->middleware('permission:' . Permission::DELETE_USERS->value)->name('employee-schedules.overrides.destroy');
        Route::get('/employees/{employee}/work-schedule', [EmployeeController::class, 'getWorkSchedule'])->name('employees.work-schedule');
    });

    // ==========================================
    // Stores
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
        Route::get('/api/stores/select', [StoreController::class, 'getForSelect'])->name('stores.select');
        Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');
        Route::post('/stores/reorder', [StoreController::class, 'reorder'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('stores.reorder');
        Route::post('/stores', [StoreController::class, 'store'])
            ->middleware(['permission:' . Permission::CREATE_USERS->value, 'plan.limit:stores'])->name('stores.store');
        Route::get('/stores/{store}', [StoreController::class, 'show'])->name('stores.show');
        Route::get('/stores/{store}/edit', [StoreController::class, 'edit'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('stores.edit');
        Route::match(['put', 'post'], '/stores/{store}', [StoreController::class, 'update'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('stores.update');
        Route::delete('/stores/{store}', [StoreController::class, 'destroy'])
            ->middleware('permission:' . Permission::DELETE_USERS->value)->name('stores.destroy');
        Route::post('/stores/{store}/activate', [StoreController::class, 'activate'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('stores.activate');
        Route::post('/stores/{store}/deactivate', [StoreController::class, 'deactivate'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('stores.deactivate');
    });

    // ==========================================
    // Color Themes
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
        Route::get('/color-themes', [ColorThemeController::class, 'index'])->name('color-themes.index');
        Route::post('/color-themes', [ColorThemeController::class, 'store'])
            ->middleware('permission:' . Permission::CREATE_USERS->value)->name('color-themes.store');
        Route::put('/color-themes/{colorTheme}', [ColorThemeController::class, 'update'])
            ->middleware('permission:' . Permission::EDIT_USERS->value)->name('color-themes.update');
        Route::delete('/color-themes/{colorTheme}', [ColorThemeController::class, 'destroy'])
            ->middleware('permission:' . Permission::DELETE_USERS->value)->name('color-themes.destroy');
    });

    // ==========================================
    // Config Modules
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::MANAGE_SETTINGS->value])->prefix('config')->name('config.')->group(function () {
        Route::resource('positions', ConfigPositionController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('position-levels', ConfigPositionLevelController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('sectors', ConfigSectorController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('genders', ConfigGenderController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('education-levels', ConfigEducationLevelController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('employee-statuses', ConfigEmployeeStatusController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('employee-event-types', ConfigEmployeeEventTypeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('type-moviments', ConfigTypeMovimentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('employment-relationships', ConfigEmploymentRelationshipController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('managers', ConfigManagerController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('networks', ConfigNetworkController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('statuses', ConfigStatusController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('page-statuses', ConfigPageStatusController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('banks', ConfigBankController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('cost-centers', ConfigCostCenterController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('payment-types', ConfigPaymentTypeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('drivers', ConfigDriverController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('stock-adjustment-statuses', ConfigStockAdjustmentStatusController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('transfer-statuses', ConfigTransferStatusController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('order-payment-statuses', ConfigOrderPaymentStatusController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('management-reasons', ConfigManagementReasonController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('percentage-awards', ConfigPercentageAwardController::class)->only(['index', 'store', 'update', 'destroy']);

        // Cadastro de Produtos - Tabelas auxiliares (CRUD + merge)
        Route::resource('product-brands', ConfigProductBrandController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('product-brands/merge-preview', [ConfigProductBrandController::class, 'mergePreview'])->name('product-brands.merge-preview');
        Route::post('product-brands/merge', [ConfigProductBrandController::class, 'merge'])->name('product-brands.merge');

        Route::resource('product-categories', ConfigProductCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('product-categories/merge-preview', [ConfigProductCategoryController::class, 'mergePreview'])->name('product-categories.merge-preview');
        Route::post('product-categories/merge', [ConfigProductCategoryController::class, 'merge'])->name('product-categories.merge');

        Route::resource('product-collections', ConfigProductCollectionController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('product-collections/merge-preview', [ConfigProductCollectionController::class, 'mergePreview'])->name('product-collections.merge-preview');
        Route::post('product-collections/merge', [ConfigProductCollectionController::class, 'merge'])->name('product-collections.merge');

        Route::resource('product-subcollections', ConfigProductSubcollectionController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('product-subcollections/merge-preview', [ConfigProductSubcollectionController::class, 'mergePreview'])->name('product-subcollections.merge-preview');
        Route::post('product-subcollections/merge', [ConfigProductSubcollectionController::class, 'merge'])->name('product-subcollections.merge');

        Route::resource('product-colors', ConfigProductColorController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('product-colors/merge-preview', [ConfigProductColorController::class, 'mergePreview'])->name('product-colors.merge-preview');
        Route::post('product-colors/merge', [ConfigProductColorController::class, 'merge'])->name('product-colors.merge');

        Route::resource('product-materials', ConfigProductMaterialController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('product-materials/merge-preview', [ConfigProductMaterialController::class, 'mergePreview'])->name('product-materials.merge-preview');
        Route::post('product-materials/merge', [ConfigProductMaterialController::class, 'merge'])->name('product-materials.merge');

        Route::resource('product-sizes', ConfigProductSizeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('product-sizes/merge-preview', [ConfigProductSizeController::class, 'mergePreview'])->name('product-sizes.merge-preview');
        Route::post('product-sizes/merge', [ConfigProductSizeController::class, 'merge'])->name('product-sizes.merge');

        Route::resource('product-article-complements', ConfigProductArticleComplementController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('product-article-complements/merge-preview', [ConfigProductArticleComplementController::class, 'mergePreview'])->name('product-article-complements.merge-preview');
        Route::post('product-article-complements/merge', [ConfigProductArticleComplementController::class, 'merge'])->name('product-article-complements.merge');
    });

    // ==========================================
    // Sales
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_SALES->value])->group(function () {
        Route::get('/sales/statistics', [SaleController::class, 'statistics'])->name('sales.statistics');
        Route::get('/sales/employee-daily', [SaleController::class, 'employeeDailySales'])->name('sales.employee-daily');
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::post('/sales', [SaleController::class, 'store'])
            ->middleware('permission:' . Permission::CREATE_SALES->value)->name('sales.store');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/sales/{sale}/edit', [SaleController::class, 'edit'])
            ->middleware('permission:' . Permission::EDIT_SALES->value)->name('sales.edit');
        Route::put('/sales/{sale}', [SaleController::class, 'update'])
            ->middleware('permission:' . Permission::EDIT_SALES->value)->name('sales.update');
        Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])
            ->middleware('permission:' . Permission::DELETE_SALES->value)->name('sales.destroy');
        Route::post('/sales/refresh-from-movements', [SaleController::class, 'refreshFromMovements'])
            ->middleware('permission:' . Permission::CREATE_SALES->value)->name('sales.refresh-from-movements');
        Route::post('/sales/bulk-delete/preview', [SaleController::class, 'bulkDeletePreview'])
            ->middleware('permission:' . Permission::DELETE_SALES->value)->name('sales.bulk-delete.preview');
        Route::post('/sales/bulk-delete', [SaleController::class, 'bulkDelete'])
            ->middleware('permission:' . Permission::DELETE_SALES->value)->name('sales.bulk-delete');
    });

    // ==========================================
    // Movements (Movimentações Diárias)
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_MOVEMENTS->value])->group(function () {
        Route::get('/movements/statistics', [MovementController::class, 'statistics'])->name('movements.statistics');
        Route::get('/movements/sync-logs', [MovementController::class, 'syncLogs'])->name('movements.sync-logs');
        Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');

        Route::middleware('permission:' . Permission::SYNC_MOVEMENTS->value)->group(function () {
            Route::post('/movements/sync/auto', [MovementController::class, 'syncAuto'])->name('movements.sync.auto');
            Route::post('/movements/sync/today', [MovementController::class, 'syncToday'])->name('movements.sync.today');
            Route::post('/movements/sync/month', [MovementController::class, 'syncByMonth'])->name('movements.sync.month');
            Route::post('/movements/sync/range', [MovementController::class, 'syncByDateRange'])->name('movements.sync.range');
            Route::post('/movements/sync/types', [MovementController::class, 'syncMovementTypes'])->name('movements.sync.types');
            Route::get('/movements/sync/{log}/progress', [MovementController::class, 'syncProgress'])->name('movements.sync.progress');
            Route::post('/movements/refresh-sales', [MovementController::class, 'refreshSalesOnly'])->name('movements.refresh-sales');
        });
    });

    // ==========================================
    // Store Goals (Metas de Loja)
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_STORE_GOALS->value])->group(function () {
        // Fixed routes (must come before {storeGoal} wildcard)
        Route::get('/store-goals/statistics', [StoreGoalController::class, 'statistics'])->name('store-goals.statistics');
        Route::get('/store-goals/achievement/consultants', [StoreGoalController::class, 'achievementByConsultant'])
            ->name('store-goals.achievement.consultants');
        Route::get('/store-goals/export/stores', [StoreGoalController::class, 'exportByStore'])
            ->name('store-goals.export.stores');
        Route::get('/store-goals/export/consultants', [StoreGoalController::class, 'exportByConsultant'])
            ->name('store-goals.export.consultants');
        Route::post('/store-goals/import', [StoreGoalController::class, 'import'])
            ->middleware('permission:' . Permission::CREATE_STORE_GOALS->value)->name('store-goals.import');

        // Resource routes
        Route::get('/store-goals', [StoreGoalController::class, 'index'])->name('store-goals.index');
        Route::post('/store-goals', [StoreGoalController::class, 'store'])
            ->middleware('permission:' . Permission::CREATE_STORE_GOALS->value)->name('store-goals.store');
        Route::get('/store-goals/{storeGoal}', [StoreGoalController::class, 'show'])->name('store-goals.show');
        Route::put('/store-goals/{storeGoal}', [StoreGoalController::class, 'update'])
            ->middleware('permission:' . Permission::EDIT_STORE_GOALS->value)->name('store-goals.update');
        Route::delete('/store-goals/{storeGoal}', [StoreGoalController::class, 'destroy'])
            ->middleware('permission:' . Permission::DELETE_STORE_GOALS->value)->name('store-goals.destroy');
        Route::post('/store-goals/{storeGoal}/redistribute', [StoreGoalController::class, 'redistribute'])
            ->middleware('permission:' . Permission::EDIT_STORE_GOALS->value)->name('store-goals.redistribute');
        Route::get('/store-goals/{storeGoal}/confirm-data', [StoreGoalController::class, 'loadConfirmData'])
            ->middleware('permission:' . Permission::EDIT_STORE_GOALS->value)->name('store-goals.confirm-data');
        Route::post('/store-goals/{storeGoal}/confirm-sales', [StoreGoalController::class, 'confirmSales'])
            ->middleware('permission:' . Permission::EDIT_STORE_GOALS->value)->name('store-goals.confirm-sales');
    });

    // ==========================================
    // Products
    // ==========================================
    Route::middleware(['auth', 'permission:' . Permission::VIEW_PRODUCTS->value])->group(function () {
        Route::post('/products/sync/init', [ProductController::class, 'syncInit'])
            ->middleware('permission:' . Permission::SYNC_PRODUCTS->value)->name('products.sync.init');
        Route::post('/products/sync/lookups', [ProductController::class, 'syncLookupsEndpoint'])
            ->middleware('permission:' . Permission::SYNC_PRODUCTS->value)->name('products.sync.lookups');
        Route::post('/products/sync/chunk', [ProductController::class, 'syncChunk'])
            ->middleware('permission:' . Permission::SYNC_PRODUCTS->value)->name('products.sync.chunk');
        Route::post('/products/sync/prices', [ProductController::class, 'syncPrices'])
            ->middleware('permission:' . Permission::SYNC_PRODUCTS->value)->name('products.sync.prices');
        Route::post('/products/sync/finalize', [ProductController::class, 'syncFinalize'])
            ->middleware('permission:' . Permission::SYNC_PRODUCTS->value)->name('products.sync.finalize');
        Route::post('/products/sync/cancel', [ProductController::class, 'syncCancel'])
            ->middleware('permission:' . Permission::SYNC_PRODUCTS->value)->name('products.sync.cancel');
        Route::get('/products/sync/status/{log}', [ProductController::class, 'syncStatus'])
            ->middleware('permission:' . Permission::SYNC_PRODUCTS->value)->name('products.sync.status');
        Route::get('/products/sync/logs', [ProductController::class, 'syncLogs'])
            ->middleware('permission:' . Permission::SYNC_PRODUCTS->value)->name('products.sync.logs');
        Route::get('/products/filter-options', [ProductController::class, 'filterOptions'])->name('products.filter-options');
        Route::get('/products/search-variants', [ProductController::class, 'searchVariants'])->name('products.search-variants');
        Route::post('/products/import-prices', [ProductController::class, 'importPrices'])
            ->middleware('permission:' . Permission::EDIT_PRODUCTS->value)->name('products.import-prices');
        Route::get('/products/import-prices/rejected/{filename}', [ProductController::class, 'downloadRejected'])
            ->middleware('permission:' . Permission::EDIT_PRODUCTS->value)->name('products.import-prices.rejected');
        Route::post('/products/print-labels', [ProductController::class, 'printLabels'])->name('products.print-labels');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
            ->middleware('permission:' . Permission::EDIT_PRODUCTS->value)->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])
            ->middleware('permission:' . Permission::EDIT_PRODUCTS->value)->name('products.update');
        Route::post('/products/{product}/unlock-sync', [ProductController::class, 'unlockSync'])
            ->middleware('permission:' . Permission::EDIT_PRODUCTS->value)->name('products.unlock-sync');
        Route::post('/products/{product}/upload-image', [ProductController::class, 'uploadImage'])
            ->middleware('permission:' . Permission::EDIT_PRODUCTS->value)->name('products.upload-image');
        Route::delete('/products/{product}/delete-image', [ProductController::class, 'deleteImage'])
            ->middleware('permission:' . Permission::EDIT_PRODUCTS->value)->name('products.delete-image');
        Route::put('/products/{product}/variants/{variant}', [ProductController::class, 'updateVariant'])
            ->middleware('permission:' . Permission::EDIT_PRODUCTS->value)->name('products.variants.update');
        Route::post('/products/{product}/variants', [ProductController::class, 'storeVariant'])
            ->middleware('permission:' . Permission::EDIT_PRODUCTS->value)->name('products.variants.store');
        Route::post('/products/{product}/variants/{variant}/generate-ean', [ProductController::class, 'generateEan'])
            ->middleware('permission:' . Permission::EDIT_PRODUCTS->value)->name('products.variants.generate-ean');
    });

    // ==========================================
    // Placeholder Routes
    // ==========================================
    Route::middleware(['auth'])->group(function () {
        Route::get('/produto', fn() => redirect('/products'))->name('produto');
        Route::get('/planejamento', fn() => Inertia::render('ComingSoon', ['title' => 'Planejamento']))->name('planejamento');
        Route::get('/financeiro', fn() => Inertia::render('ComingSoon', ['title' => 'Financeiro']))->name('financeiro');
        Route::get('/ativo-fixo', fn() => Inertia::render('ComingSoon', ['title' => 'Ativo Fixo']))->name('ativo-fixo');
        Route::get('/comercial', fn() => redirect('/sales'))->name('comercial');
        Route::get('/delivery', fn() => Inertia::render('ComingSoon', ['title' => 'Delivery']))->name('delivery');
        Route::get('/rotas', fn() => Inertia::render('ComingSoon', ['title' => 'Rotas']))->name('rotas');

        // ==========================================
        // User Sessions
        // ==========================================
        Route::middleware('permission:' . Permission::VIEW_USER_SESSIONS->value)->group(function () {
            Route::get('/user-sessions', [UserSessionController::class, 'index'])->name('user-sessions.index');
        });
        Route::post('/user-sessions/heartbeat', [UserSessionController::class, 'heartbeat'])->name('user-sessions.heartbeat');

        // ==========================================
        // Transfers
        // ==========================================
        Route::middleware('permission:' . Permission::VIEW_TRANSFERS->value)->group(function () {
            Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
            Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
            Route::middleware('permission:' . Permission::CREATE_TRANSFERS->value)->group(function () {
                Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
            });
            Route::middleware('permission:' . Permission::EDIT_TRANSFERS->value)->group(function () {
                Route::put('/transfers/{transfer}', [TransferController::class, 'update'])->name('transfers.update');
                Route::post('/transfers/{transfer}/confirm-pickup', [TransferController::class, 'confirmPickup'])->name('transfers.confirm-pickup');
                Route::post('/transfers/{transfer}/confirm-delivery', [TransferController::class, 'confirmDelivery'])->name('transfers.confirm-delivery');
                Route::post('/transfers/{transfer}/confirm-receipt', [TransferController::class, 'confirmReceipt'])->name('transfers.confirm-receipt');
                Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
            });
            Route::middleware('permission:' . Permission::DELETE_TRANSFERS->value)->group(function () {
                Route::delete('/transfers/{transfer}', [TransferController::class, 'destroy'])->name('transfers.destroy');
            });
        });

        // ==========================================
        // Stock Adjustments
        // ==========================================
        Route::middleware('permission:' . Permission::VIEW_ADJUSTMENTS->value)->group(function () {
            Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
            Route::get('/stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'show'])->name('stock-adjustments.show');
            Route::middleware('permission:' . Permission::CREATE_ADJUSTMENTS->value)->group(function () {
                Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
            });
            Route::middleware('permission:' . Permission::EDIT_ADJUSTMENTS->value)->group(function () {
                Route::put('/stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'update'])->name('stock-adjustments.update');
                Route::post('/stock-adjustments/{stockAdjustment}/transition', [StockAdjustmentController::class, 'transition'])->name('stock-adjustments.transition');
            });
            Route::middleware('permission:' . Permission::DELETE_ADJUSTMENTS->value)->group(function () {
                Route::delete('/stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'destroy'])->name('stock-adjustments.destroy');
            });
        });

        // ==========================================
        // Order Payments
        // ==========================================
        Route::middleware('permission:' . Permission::VIEW_ORDER_PAYMENTS->value)->group(function () {
            Route::get('/order-payments', [OrderPaymentController::class, 'index'])->name('order-payments.index');
            Route::get('/order-payments/statistics', [OrderPaymentController::class, 'statistics'])->name('order-payments.statistics');
            Route::get('/order-payments/{orderPayment}', [OrderPaymentController::class, 'show'])->name('order-payments.show');
            Route::get('/order-payments/{orderPayment}/delete-check', [OrderPaymentController::class, 'checkDeletePermission'])->name('order-payments.delete-check');
            Route::middleware('permission:' . Permission::CREATE_ORDER_PAYMENTS->value)->group(function () {
                Route::post('/order-payments', [OrderPaymentController::class, 'store'])->name('order-payments.store');
            });
            Route::middleware('permission:' . Permission::EDIT_ORDER_PAYMENTS->value)->group(function () {
                Route::put('/order-payments/{orderPayment}', [OrderPaymentController::class, 'update'])->name('order-payments.update');
                Route::post('/order-payments/{orderPayment}/transition', [OrderPaymentController::class, 'transition'])->name('order-payments.transition');
                Route::post('/order-payments/bulk-transition', [OrderPaymentController::class, 'bulkTransition'])->name('order-payments.bulk-transition');
                Route::post('/order-payments/{orderPayment}/allocations', [OrderPaymentController::class, 'saveAllocations'])->name('order-payments.allocations');
                Route::post('/order-payments/{orderPayment}/restore', [OrderPaymentController::class, 'restore'])->name('order-payments.restore');
                Route::post('/order-payment-installments/{installment}/mark-paid', [OrderPaymentController::class, 'markInstallmentPaid'])->name('order-payments.installment-mark-paid');
            });
            Route::middleware('permission:' . Permission::DELETE_ORDER_PAYMENTS->value)->group(function () {
                Route::delete('/order-payments/{orderPayment}', [OrderPaymentController::class, 'destroy'])->name('order-payments.destroy');
            });
        });

        // ==========================================
        // Suppliers
        // ==========================================
        Route::middleware('permission:' . Permission::VIEW_SUPPLIERS->value)->group(function () {
            Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
            Route::middleware('permission:' . Permission::CREATE_SUPPLIERS->value)->group(function () {
                Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            });
            Route::middleware('permission:' . Permission::EDIT_SUPPLIERS->value)->group(function () {
                Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            });
            Route::middleware('permission:' . Permission::DELETE_SUPPLIERS->value)->group(function () {
                Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
            });
        });

        // ==========================================
        // Medical Certificates
        // ==========================================
        Route::middleware('permission:' . Permission::VIEW_MEDICAL_CERTIFICATES->value)->group(function () {
            Route::get('/medical-certificates', [MedicalCertificateController::class, 'index'])->name('medical-certificates.index');
            Route::get('/medical-certificates/{medical_certificate}', [MedicalCertificateController::class, 'show'])->name('medical-certificates.show');
            Route::middleware('permission:' . Permission::CREATE_MEDICAL_CERTIFICATES->value)->group(function () {
                Route::post('/medical-certificates', [MedicalCertificateController::class, 'store'])->name('medical-certificates.store');
            });
            Route::middleware('permission:' . Permission::EDIT_MEDICAL_CERTIFICATES->value)->group(function () {
                Route::put('/medical-certificates/{medical_certificate}', [MedicalCertificateController::class, 'update'])->name('medical-certificates.update');
            });
            Route::middleware('permission:' . Permission::DELETE_MEDICAL_CERTIFICATES->value)->group(function () {
                Route::delete('/medical-certificates/{medical_certificate}', [MedicalCertificateController::class, 'destroy'])->name('medical-certificates.destroy');
            });
        });

        // ==========================================
        // Absences
        // ==========================================
        Route::middleware('permission:' . Permission::VIEW_ABSENCES->value)->group(function () {
            Route::get('/absences', [AbsenceController::class, 'index'])->name('absences.index');
            Route::get('/absences/{absence}', [AbsenceController::class, 'show'])->name('absences.show');
            Route::middleware('permission:' . Permission::CREATE_ABSENCES->value)->group(function () {
                Route::post('/absences', [AbsenceController::class, 'store'])->name('absences.store');
            });
            Route::middleware('permission:' . Permission::EDIT_ABSENCES->value)->group(function () {
                Route::put('/absences/{absence}', [AbsenceController::class, 'update'])->name('absences.update');
            });
            Route::middleware('permission:' . Permission::DELETE_ABSENCES->value)->group(function () {
                Route::delete('/absences/{absence}', [AbsenceController::class, 'destroy'])->name('absences.destroy');
            });
        });

        // ==========================================
        // Overtime Records
        // ==========================================
        Route::middleware('permission:' . Permission::VIEW_OVERTIME->value)->group(function () {
            Route::get('/overtime-records', [OvertimeRecordController::class, 'index'])->name('overtime-records.index');
            Route::get('/overtime-records/{overtime_record}', [OvertimeRecordController::class, 'show'])->name('overtime-records.show');
            Route::middleware('permission:' . Permission::CREATE_OVERTIME->value)->group(function () {
                Route::post('/overtime-records', [OvertimeRecordController::class, 'store'])->name('overtime-records.store');
            });
            Route::middleware('permission:' . Permission::EDIT_OVERTIME->value)->group(function () {
                Route::put('/overtime-records/{overtime_record}', [OvertimeRecordController::class, 'update'])->name('overtime-records.update');
            });
            Route::middleware('permission:' . Permission::DELETE_OVERTIME->value)->group(function () {
                Route::delete('/overtime-records/{overtime_record}', [OvertimeRecordController::class, 'destroy'])->name('overtime-records.destroy');
            });
        });

        // ==========================================
        // Checklists
        // ==========================================
        Route::middleware('permission:' . Permission::VIEW_CHECKLISTS->value)->group(function () {
            Route::get('/checklists', [ChecklistController::class, 'index'])->name('checklists.index');
            Route::get('/checklists/employees', [ChecklistController::class, 'employees'])->name('checklists.employees');
            Route::get('/checklists/{checklist}', [ChecklistController::class, 'show'])->name('checklists.show');
            Route::get('/checklists/{checklist}/statistics', [ChecklistController::class, 'statistics'])->name('checklists.statistics');
            Route::middleware('permission:' . Permission::CREATE_CHECKLISTS->value)->group(function () {
                Route::post('/checklists', [ChecklistController::class, 'store'])->name('checklists.store');
            });
            Route::middleware('permission:' . Permission::EDIT_CHECKLISTS->value)->group(function () {
                Route::put('/checklists/{checklist}/answers/{answer}', [ChecklistController::class, 'updateAnswer'])->name('checklists.update-answer');
            });
            Route::middleware('permission:' . Permission::DELETE_CHECKLISTS->value)->group(function () {
                Route::delete('/checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklists.destroy');
            });
        });

        // ==========================================
        // Integrations
        // ==========================================
        Route::middleware('permission:' . Permission::MANAGE_SETTINGS->value)->prefix('integrations')->name('integrations.')->group(function () {
            Route::get('/', [IntegrationController::class, 'index'])->name('index');
            Route::post('/', [IntegrationController::class, 'store'])->name('store');
            Route::get('/{integration}', [IntegrationController::class, 'show'])->name('show');
            Route::put('/{integration}', [IntegrationController::class, 'update'])->name('update');
            Route::delete('/{integration}', [IntegrationController::class, 'destroy'])->name('destroy');
            Route::post('/{integration}/test', [IntegrationController::class, 'testConnection'])->name('test');
            Route::post('/{integration}/sync', [IntegrationController::class, 'triggerSync'])->name('sync');
            Route::get('/{integration}/logs', [IntegrationController::class, 'syncLogs'])->name('logs');
        });

        // ==========================================
        // Placeholder Coming Soon
        // ==========================================
        Route::get('/ecommerce', fn() => Inertia::render('ComingSoon', ['title' => 'E-commerce']))->name('ecommerce');
        Route::get('/pessoas-cultura', fn() => Inertia::render('ComingSoon', ['title' => 'Pessoas & Cultura']))->name('pessoas-cultura');
        Route::get('/departamento-pessoal', fn() => Inertia::render('ComingSoon', ['title' => 'Departamento Pessoal']))->name('departamento-pessoal');
        Route::get('/escola-digital', fn() => Inertia::render('ComingSoon', ['title' => 'Escola Digital']))->name('escola-digital');
        Route::get('/movidesk', fn() => Inertia::render('ComingSoon', ['title' => 'Movidesk']))->name('movidesk');
        Route::get('/biblioteca-processos', fn() => Inertia::render('ComingSoon', ['title' => 'Biblioteca de Processos']))->name('biblioteca-processos');
    });
