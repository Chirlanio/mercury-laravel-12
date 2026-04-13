<?php

// Shared route definitions for tenant context.
// Included by both tenant.php (with tenancy middleware) and tenant-test.php (without).

use App\Enums\Permission;
use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ChatBroadcastController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatGroupController;
use App\Http\Controllers\HdPermissionController;
use App\Http\Controllers\HelpdeskController;
use App\Http\Controllers\HelpdeskReportController;
use App\Http\Controllers\HelpdeskSavedViewController;
use App\Http\Controllers\Config\HdCategoryController as ConfigHdCategoryController;
use App\Http\Controllers\Config\HdDepartmentController as ConfigHdDepartmentController;
use App\Http\Controllers\Admin\EmailSettingsController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ColorThemeController;
use App\Http\Controllers\Config\BankController as ConfigBankController;
use App\Http\Controllers\Config\CostCenterController as ConfigCostCenterController;
use App\Http\Controllers\Config\DriverController as ConfigDriverController;
use App\Http\Controllers\Config\EducationLevelController as ConfigEducationLevelController;
use App\Http\Controllers\Config\EmployeeEventTypeController as ConfigEmployeeEventTypeController;
use App\Http\Controllers\Config\EmployeeStatusController as ConfigEmployeeStatusController;
use App\Http\Controllers\Config\EmploymentRelationshipController as ConfigEmploymentRelationshipController;
use App\Http\Controllers\Config\GenderController as ConfigGenderController;
use App\Http\Controllers\Config\DeliveryReturnReasonController as ConfigDeliveryReturnReasonController;
use App\Http\Controllers\Config\ManagementReasonController as ConfigManagementReasonController;
use App\Http\Controllers\Config\ManagerController as ConfigManagerController;
use App\Http\Controllers\Config\NetworkController as ConfigNetworkController;
use App\Http\Controllers\Config\OrderPaymentStatusController as ConfigOrderPaymentStatusController;
use App\Http\Controllers\Config\PageStatusController as ConfigPageStatusController;
use App\Http\Controllers\Config\PaymentTypeController as ConfigPaymentTypeController;
use App\Http\Controllers\Config\PercentageAwardController as ConfigPercentageAwardController;
use App\Http\Controllers\Config\PositionController as ConfigPositionController;
use App\Http\Controllers\Config\PositionLevelController as ConfigPositionLevelController;
use App\Http\Controllers\Config\ProductArticleComplementController as ConfigProductArticleComplementController;
use App\Http\Controllers\Config\ProductBrandController as ConfigProductBrandController;
use App\Http\Controllers\Config\ProductCategoryController as ConfigProductCategoryController;
use App\Http\Controllers\Config\ProductCollectionController as ConfigProductCollectionController;
use App\Http\Controllers\Config\ProductColorController as ConfigProductColorController;
use App\Http\Controllers\Config\ProductLookupGroupController as ConfigProductLookupGroupController;
use App\Http\Controllers\Config\ProductMaterialController as ConfigProductMaterialController;
use App\Http\Controllers\Config\ProductSizeController as ConfigProductSizeController;
use App\Http\Controllers\Config\ProductSubcollectionController as ConfigProductSubcollectionController;
use App\Http\Controllers\Config\SectorController as ConfigSectorController;
use App\Http\Controllers\Config\StatusController as ConfigStatusController;
use App\Http\Controllers\Config\StockAdjustmentReasonController as ConfigStockAdjustmentReasonController;
use App\Http\Controllers\Config\StockAdjustmentStatusController as ConfigStockAdjustmentStatusController;
use App\Http\Controllers\Config\StockAuditCycleController as ConfigStockAuditCycleController;
use App\Http\Controllers\Config\StockAuditVendorController as ConfigStockAuditVendorController;
use App\Http\Controllers\Config\TransferStatusController as ConfigTransferStatusController;
use App\Http\Controllers\Config\TypeMovimentController as ConfigTypeMovimentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryRouteController;
use App\Http\Controllers\ExperienceTrackerController;
use App\Http\Controllers\PublicCourseController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\LgpdController;
use App\Http\Controllers\MedicalCertificateController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\OrderPaymentController;
use App\Http\Controllers\OvertimeRecordController;
use App\Http\Controllers\PersonnelMovementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockAuditController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreGoalController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TrainingContentController;
use App\Http\Controllers\TrainingCourseController;
use App\Http\Controllers\TrainingEventController;
use App\Http\Controllers\TrainingQuizController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\UserSessionController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\WorkScheduleController;
use App\Http\Controllers\WorkShiftController;
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
    ->middleware(['auth', 'verified', 'permission:'.Permission::ACCESS_DASHBOARD->value])
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
        ->middleware('permission:'.Permission::VIEW_OWN_PROFILE->value)
        ->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('permission:'.Permission::EDIT_OWN_PROFILE->value)
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('permission:'.Permission::EDIT_OWN_PROFILE->value)
        ->name('profile.destroy');
});

// ==========================================
// Admin & Support Panels
// ==========================================
Route::middleware(['auth', 'permission:'.Permission::ACCESS_ADMIN_PANEL->value])->group(function () {
    Route::get('/admin', function () {
        return Inertia::render('Admin');
    })->name('admin');

    Route::middleware('permission:'.Permission::MANAGE_SETTINGS->value)->group(function () {
        Route::get('/admin/email-settings', [EmailSettingsController::class, 'index'])->name('admin.email-settings');
    });
});

Route::middleware(['auth', 'permission:'.Permission::ACCESS_SUPPORT_PANEL->value])->group(function () {
    Route::get('/support', function () {
        return Inertia::render('Support');
    })->name('support');
});

// ==========================================
// User Management
// ==========================================
Route::middleware(['auth', 'tenant.module:users'])->group(function () {
    Route::get('/users', [UserManagementController::class, 'index'])
        ->middleware('permission:'.Permission::VIEW_USERS->value)
        ->name('users.index');
    Route::get('/users/create', [UserManagementController::class, 'create'])
        ->middleware('permission:'.Permission::CREATE_USERS->value)
        ->name('users.create');
    Route::post('/users', [UserManagementController::class, 'store'])
        ->middleware(['permission:'.Permission::CREATE_USERS->value, 'plan.limit:users'])
        ->name('users.store');
    Route::get('/users/{user}', [UserManagementController::class, 'show'])
        ->middleware('permission:'.Permission::VIEW_USERS->value.','.Permission::VIEW_ANY_PROFILE->value)
        ->name('users.show');
    Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])
        ->middleware('permission:'.Permission::EDIT_USERS->value.','.Permission::EDIT_ANY_PROFILE->value)
        ->name('users.edit');
    Route::match(['put', 'post'], '/users/{user}', [UserManagementController::class, 'update'])
        ->middleware('permission:'.Permission::EDIT_USERS->value.','.Permission::EDIT_ANY_PROFILE->value)
        ->name('users.update');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
        ->middleware('permission:'.Permission::DELETE_USERS->value)
        ->name('users.destroy');
    Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole'])
        ->middleware('permission:'.Permission::MANAGE_USER_ROLES->value)
        ->name('users.updateRole');
    Route::delete('/users/{user}/avatar', [UserManagementController::class, 'removeAvatar'])
        ->middleware('permission:'.Permission::EDIT_USERS->value.','.Permission::EDIT_ANY_PROFILE->value)
        ->name('users.removeAvatar');
});

// ==========================================
// Activity Logs
// ==========================================
Route::middleware(['auth', 'tenant.module:activity_logs', 'permission:'.Permission::VIEW_ACTIVITY_LOGS->value])->group(function () {
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-logs/{log}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
    Route::post('/activity-logs/export', [ActivityLogController::class, 'export'])
        ->middleware('permission:'.Permission::EXPORT_ACTIVITY_LOGS->value)
        ->name('activity-logs.export');
    Route::delete('/activity-logs/cleanup', [ActivityLogController::class, 'cleanup'])
        ->middleware('permission:'.Permission::MANAGE_SYSTEM_SETTINGS->value)
        ->name('activity-logs.cleanup');
});

// ==========================================
// Menus API (dynamic sidebar - kept for menu rendering)
// ==========================================
Route::middleware('auth')->get('/api/menus/sidebar', [MenuController::class, 'getSidebarMenus'])->name('menus.sidebar');
Route::middleware('auth')->get('/api/menus/dynamic-sidebar', [MenuController::class, 'getDynamicSidebarMenus'])->name('menus.dynamic-sidebar');

// Menus, Pages, Page Groups, Access Levels — gerenciados exclusivamente
// pelo Admin Central em /admin/navigation. Rotas CRUD removidas do tenant.

// ==========================================
// Employees
// ==========================================
Route::middleware(['auth', 'tenant.module:employees', 'permission:'.Permission::VIEW_USERS->value])->group(function () {
    Route::get('/employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::get('/employees/list-json', [EmployeeController::class, 'listJson'])->name('employees.list-json');
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])
        ->middleware('permission:'.Permission::CREATE_USERS->value)
        ->name('employees.store');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)
        ->name('employees.edit');
    Route::match(['put', 'post'], '/employees/{employee}', [EmployeeController::class, 'update'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)
        ->name('employees.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('permission:'.Permission::DELETE_USERS->value)
        ->name('employees.destroy');
    Route::get('/employees/{employee}/history', [EmployeeController::class, 'history'])->name('employees.history');
    Route::post('/employees/{employee}/contracts', [EmployeeController::class, 'storeContract'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('employees.contracts.store');
    Route::put('/employees/{employee}/contracts/{contract}', [EmployeeController::class, 'updateContract'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('employees.contracts.update');
    Route::delete('/employees/{employee}/contracts/{contract}', [EmployeeController::class, 'destroyContract'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('employees.contracts.destroy');
    Route::post('/employees/{employee}/contracts/{contract}/reactivate', [EmployeeController::class, 'reactivateContract'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('employees.contracts.reactivate');
    Route::get('/employees/{employee}/events', [EmployeeController::class, 'getEvents'])->name('employees.events.index');
    Route::post('/employees/{employee}/events', [EmployeeController::class, 'storeEvent'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('employees.events.store');
    Route::delete('/employees/{employee}/events/{event}', [EmployeeController::class, 'destroyEvent'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('employees.events.destroy');
    Route::get('/employees/{employee}/events/export', [EmployeeController::class, 'exportEvents'])->name('employees.events.export');
    Route::get('/employees/{employee}/report', [EmployeeController::class, 'generateReport'])->name('employees.report');
    Route::get('/employees/events/export', [EmployeeController::class, 'exportAllEvents'])->name('employees.all-events.export');
});

// ==========================================
// Work Shifts
// ==========================================
Route::middleware(['auth', 'tenant.module:work_shifts', 'permission:'.Permission::VIEW_USERS->value])->group(function () {
    Route::get('/work-shifts', [WorkShiftController::class, 'index'])->name('work-shifts.index');
    Route::get('/work-shifts/export', [WorkShiftController::class, 'export'])->name('work-shifts.export');
    Route::get('/work-shifts/print-summary', [WorkShiftController::class, 'printSummary'])->name('work-shifts.print-summary');
    Route::post('/work-shifts', [WorkShiftController::class, 'store'])
        ->middleware('permission:'.Permission::CREATE_USERS->value)->name('work-shifts.store');
    Route::get('/work-shifts/{workShift}', [WorkShiftController::class, 'show'])->name('work-shifts.show');
    Route::get('/work-shifts/{workShift}/edit', [WorkShiftController::class, 'edit'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('work-shifts.edit');
    Route::match(['put', 'post'], '/work-shifts/{workShift}', [WorkShiftController::class, 'update'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('work-shifts.update');
    Route::delete('/work-shifts/{workShift}', [WorkShiftController::class, 'destroy'])
        ->middleware('permission:'.Permission::DELETE_USERS->value)->name('work-shifts.destroy');
});

// ==========================================
// Work Schedules
// ==========================================
Route::middleware(['auth', 'tenant.module:work_schedules', 'permission:'.Permission::VIEW_USERS->value])->group(function () {
    Route::get('/work-schedules', [WorkScheduleController::class, 'index'])->name('work-schedules.index');
    Route::get('/work-schedules/list-json', [WorkScheduleController::class, 'listJson'])->name('work-schedules.list-json');
    Route::get('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'show'])->name('work-schedules.show');
    Route::get('/work-schedules/{workSchedule}/edit', [WorkScheduleController::class, 'edit'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('work-schedules.edit');
    Route::get('/work-schedules/{workSchedule}/employees', [WorkScheduleController::class, 'getEmployees'])->name('work-schedules.employees');
    Route::post('/work-schedules', [WorkScheduleController::class, 'store'])
        ->middleware('permission:'.Permission::CREATE_USERS->value)->name('work-schedules.store');
    Route::post('/work-schedules/{workSchedule}/duplicate', [WorkScheduleController::class, 'duplicate'])
        ->middleware('permission:'.Permission::CREATE_USERS->value)->name('work-schedules.duplicate');
    Route::post('/work-schedules/{workSchedule}/employees', [WorkScheduleController::class, 'assignEmployee'])
        ->middleware('permission:'.Permission::CREATE_USERS->value)->name('work-schedules.assign-employee');
    Route::match(['put', 'post'], '/work-schedules/{workSchedule}/update', [WorkScheduleController::class, 'update'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('work-schedules.update');
    Route::delete('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'destroy'])
        ->middleware('permission:'.Permission::DELETE_USERS->value)->name('work-schedules.destroy');
    Route::delete('/work-schedules/{workSchedule}/employees/{assignment}', [WorkScheduleController::class, 'unassignEmployee'])
        ->middleware('permission:'.Permission::DELETE_USERS->value)->name('work-schedules.unassign-employee');
    Route::post('/employee-schedules/{assignment}/overrides', [WorkScheduleController::class, 'storeOverride'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('employee-schedules.overrides.store');
    Route::delete('/employee-schedules/{assignment}/overrides/{override}', [WorkScheduleController::class, 'destroyOverride'])
        ->middleware('permission:'.Permission::DELETE_USERS->value)->name('employee-schedules.overrides.destroy');
    Route::get('/employees/{employee}/work-schedule', [EmployeeController::class, 'getWorkSchedule'])->name('employees.work-schedule');
});

// ==========================================
// Stores
// ==========================================
Route::middleware(['auth', 'tenant.module:stores', 'permission:'.Permission::VIEW_USERS->value])->group(function () {
    Route::get('/api/stores/select', [StoreController::class, 'getForSelect'])->name('stores.select');
    Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');
    Route::post('/stores/reorder', [StoreController::class, 'reorder'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('stores.reorder');
    Route::post('/stores', [StoreController::class, 'store'])
        ->middleware(['permission:'.Permission::CREATE_USERS->value, 'plan.limit:stores'])->name('stores.store');
    Route::get('/stores/{store}', [StoreController::class, 'show'])->name('stores.show');
    Route::get('/stores/{store}/edit', [StoreController::class, 'edit'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('stores.edit');
    Route::match(['put', 'post'], '/stores/{store}', [StoreController::class, 'update'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('stores.update');
    Route::delete('/stores/{store}', [StoreController::class, 'destroy'])
        ->middleware('permission:'.Permission::DELETE_USERS->value)->name('stores.destroy');
    Route::post('/stores/{store}/activate', [StoreController::class, 'activate'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('stores.activate');
    Route::post('/stores/{store}/deactivate', [StoreController::class, 'deactivate'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('stores.deactivate');
});

// ==========================================
// Color Themes
// ==========================================
Route::middleware(['auth', 'tenant.module:color_themes', 'permission:'.Permission::VIEW_USERS->value])->group(function () {
    Route::get('/color-themes', [ColorThemeController::class, 'index'])->name('color-themes.index');
    Route::post('/color-themes', [ColorThemeController::class, 'store'])
        ->middleware('permission:'.Permission::CREATE_USERS->value)->name('color-themes.store');
    Route::put('/color-themes/{colorTheme}', [ColorThemeController::class, 'update'])
        ->middleware('permission:'.Permission::EDIT_USERS->value)->name('color-themes.update');
    Route::delete('/color-themes/{colorTheme}', [ColorThemeController::class, 'destroy'])
        ->middleware('permission:'.Permission::DELETE_USERS->value)->name('color-themes.destroy');
});

// ==========================================
// Config Modules
// ==========================================
Route::middleware(['auth', 'tenant.module:config', 'permission:'.Permission::MANAGE_SETTINGS->value])->prefix('config')->name('config.')->group(function () {
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
    Route::resource('stock-adjustment-reasons', ConfigStockAdjustmentReasonController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('transfer-statuses', ConfigTransferStatusController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('order-payment-statuses', ConfigOrderPaymentStatusController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('management-reasons', ConfigManagementReasonController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('delivery-return-reasons', ConfigDeliveryReturnReasonController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('percentage-awards', ConfigPercentageAwardController::class)->only(['index', 'store', 'update', 'destroy']);

    // Cadastro de Produtos - Grupos e Tabelas auxiliares
    Route::resource('product-lookup-groups', ConfigProductLookupGroupController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('product-brands', ConfigProductBrandController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('product-brands/assign-group', [ConfigProductBrandController::class, 'assignGroup'])->name('product-brands.assign-group');
    Route::resource('product-categories', ConfigProductCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('product-categories/assign-group', [ConfigProductCategoryController::class, 'assignGroup'])->name('product-categories.assign-group');
    Route::resource('product-collections', ConfigProductCollectionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('product-collections/assign-group', [ConfigProductCollectionController::class, 'assignGroup'])->name('product-collections.assign-group');
    Route::resource('product-subcollections', ConfigProductSubcollectionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('product-subcollections/assign-group', [ConfigProductSubcollectionController::class, 'assignGroup'])->name('product-subcollections.assign-group');
    Route::resource('product-colors', ConfigProductColorController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('product-colors/assign-group', [ConfigProductColorController::class, 'assignGroup'])->name('product-colors.assign-group');
    Route::resource('product-materials', ConfigProductMaterialController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('product-materials/assign-group', [ConfigProductMaterialController::class, 'assignGroup'])->name('product-materials.assign-group');
    Route::resource('product-sizes', ConfigProductSizeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('product-sizes/assign-group', [ConfigProductSizeController::class, 'assignGroup'])->name('product-sizes.assign-group');
    Route::resource('product-article-complements', ConfigProductArticleComplementController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('product-article-complements/assign-group', [ConfigProductArticleComplementController::class, 'assignGroup'])->name('product-article-complements.assign-group');
});

// ==========================================
// Sales
// ==========================================
Route::middleware(['auth', 'tenant.module:sales', 'permission:'.Permission::VIEW_SALES->value])->group(function () {
    Route::get('/sales/statistics', [SaleController::class, 'statistics'])->name('sales.statistics');
    Route::get('/sales/employee-daily', [SaleController::class, 'employeeDailySales'])->name('sales.employee-daily');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::post('/sales', [SaleController::class, 'store'])
        ->middleware('permission:'.Permission::CREATE_SALES->value)->name('sales.store');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('/sales/{sale}/edit', [SaleController::class, 'edit'])
        ->middleware('permission:'.Permission::EDIT_SALES->value)->name('sales.edit');
    Route::put('/sales/{sale}', [SaleController::class, 'update'])
        ->middleware('permission:'.Permission::EDIT_SALES->value)->name('sales.update');
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])
        ->middleware('permission:'.Permission::DELETE_SALES->value)->name('sales.destroy');
    Route::post('/sales/refresh-from-movements', [SaleController::class, 'refreshFromMovements'])
        ->middleware('permission:'.Permission::CREATE_SALES->value)->name('sales.refresh-from-movements');
    Route::post('/sales/bulk-delete/preview', [SaleController::class, 'bulkDeletePreview'])
        ->middleware('permission:'.Permission::DELETE_SALES->value)->name('sales.bulk-delete.preview');
    Route::post('/sales/bulk-delete', [SaleController::class, 'bulkDelete'])
        ->middleware('permission:'.Permission::DELETE_SALES->value)->name('sales.bulk-delete');
});

// ==========================================
// Movements (Movimentações Diárias)
// ==========================================
Route::middleware(['auth', 'tenant.module:movements', 'permission:'.Permission::VIEW_MOVEMENTS->value])->group(function () {
    Route::get('/movements/statistics', [MovementController::class, 'statistics'])->name('movements.statistics');
    Route::get('/movements/sync-logs', [MovementController::class, 'syncLogs'])->name('movements.sync-logs');
    Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');

    Route::middleware('permission:'.Permission::SYNC_MOVEMENTS->value)->group(function () {
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
Route::middleware(['auth', 'tenant.module:store_goals', 'permission:'.Permission::VIEW_STORE_GOALS->value])->group(function () {
    // Fixed routes (must come before {storeGoal} wildcard)
    Route::get('/store-goals/statistics', [StoreGoalController::class, 'statistics'])->name('store-goals.statistics');
    Route::get('/store-goals/achievement/consultants', [StoreGoalController::class, 'achievementByConsultant'])
        ->name('store-goals.achievement.consultants');
    Route::get('/store-goals/export/stores', [StoreGoalController::class, 'exportByStore'])
        ->name('store-goals.export.stores');
    Route::get('/store-goals/export/consultants', [StoreGoalController::class, 'exportByConsultant'])
        ->name('store-goals.export.consultants');
    Route::post('/store-goals/import', [StoreGoalController::class, 'import'])
        ->middleware('permission:'.Permission::CREATE_STORE_GOALS->value)->name('store-goals.import');

    // Resource routes
    Route::get('/store-goals', [StoreGoalController::class, 'index'])->name('store-goals.index');
    Route::post('/store-goals', [StoreGoalController::class, 'store'])
        ->middleware('permission:'.Permission::CREATE_STORE_GOALS->value)->name('store-goals.store');
    Route::get('/store-goals/{storeGoal}', [StoreGoalController::class, 'show'])->name('store-goals.show');
    Route::put('/store-goals/{storeGoal}', [StoreGoalController::class, 'update'])
        ->middleware('permission:'.Permission::EDIT_STORE_GOALS->value)->name('store-goals.update');
    Route::delete('/store-goals/{storeGoal}', [StoreGoalController::class, 'destroy'])
        ->middleware('permission:'.Permission::DELETE_STORE_GOALS->value)->name('store-goals.destroy');
    Route::post('/store-goals/{storeGoal}/redistribute', [StoreGoalController::class, 'redistribute'])
        ->middleware('permission:'.Permission::EDIT_STORE_GOALS->value)->name('store-goals.redistribute');
    Route::get('/store-goals/{storeGoal}/confirm-data', [StoreGoalController::class, 'loadConfirmData'])
        ->middleware('permission:'.Permission::EDIT_STORE_GOALS->value)->name('store-goals.confirm-data');
    Route::post('/store-goals/{storeGoal}/confirm-sales', [StoreGoalController::class, 'confirmSales'])
        ->middleware('permission:'.Permission::EDIT_STORE_GOALS->value)->name('store-goals.confirm-sales');
});

// ==========================================
// Products
// ==========================================
Route::middleware(['auth', 'tenant.module:products', 'permission:'.Permission::VIEW_PRODUCTS->value])->group(function () {
    Route::post('/products/sync/init', [ProductController::class, 'syncInit'])
        ->middleware('permission:'.Permission::SYNC_PRODUCTS->value)->name('products.sync.init');
    Route::post('/products/sync/lookups', [ProductController::class, 'syncLookupsEndpoint'])
        ->middleware('permission:'.Permission::SYNC_PRODUCTS->value)->name('products.sync.lookups');
    Route::post('/products/sync/chunk', [ProductController::class, 'syncChunk'])
        ->middleware('permission:'.Permission::SYNC_PRODUCTS->value)->name('products.sync.chunk');
    Route::post('/products/sync/prices', [ProductController::class, 'syncPrices'])
        ->middleware('permission:'.Permission::SYNC_PRODUCTS->value)->name('products.sync.prices');
    Route::post('/products/sync/finalize', [ProductController::class, 'syncFinalize'])
        ->middleware('permission:'.Permission::SYNC_PRODUCTS->value)->name('products.sync.finalize');
    Route::post('/products/sync/cancel', [ProductController::class, 'syncCancel'])
        ->middleware('permission:'.Permission::SYNC_PRODUCTS->value)->name('products.sync.cancel');
    Route::get('/products/sync/status/{log}', [ProductController::class, 'syncStatus'])
        ->middleware('permission:'.Permission::SYNC_PRODUCTS->value)->name('products.sync.status');
    Route::get('/products/sync/logs', [ProductController::class, 'syncLogs'])
        ->middleware('permission:'.Permission::SYNC_PRODUCTS->value)->name('products.sync.logs');
    Route::get('/products/filter-options', [ProductController::class, 'filterOptions'])->name('products.filter-options');
    Route::get('/products/search-variants', [ProductController::class, 'searchVariants'])->name('products.search-variants');
    Route::post('/products/import-prices', [ProductController::class, 'importPrices'])
        ->middleware('permission:'.Permission::EDIT_PRODUCTS->value)->name('products.import-prices');
    Route::get('/products/import-prices/rejected/{filename}', [ProductController::class, 'downloadRejected'])
        ->middleware('permission:'.Permission::EDIT_PRODUCTS->value)->name('products.import-prices.rejected');
    Route::post('/products/print-labels', [ProductController::class, 'printLabels'])->name('products.print-labels');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->middleware('permission:'.Permission::EDIT_PRODUCTS->value)->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:'.Permission::EDIT_PRODUCTS->value)->name('products.update');
    Route::post('/products/{product}/unlock-sync', [ProductController::class, 'unlockSync'])
        ->middleware('permission:'.Permission::EDIT_PRODUCTS->value)->name('products.unlock-sync');
    Route::post('/products/{product}/upload-image', [ProductController::class, 'uploadImage'])
        ->middleware('permission:'.Permission::EDIT_PRODUCTS->value)->name('products.upload-image');
    Route::delete('/products/{product}/delete-image', [ProductController::class, 'deleteImage'])
        ->middleware('permission:'.Permission::EDIT_PRODUCTS->value)->name('products.delete-image');
    Route::put('/products/{product}/variants/{variant}', [ProductController::class, 'updateVariant'])
        ->middleware('permission:'.Permission::EDIT_PRODUCTS->value)->name('products.variants.update');
    Route::post('/products/{product}/variants', [ProductController::class, 'storeVariant'])
        ->middleware('permission:'.Permission::EDIT_PRODUCTS->value)->name('products.variants.store');
    Route::post('/products/{product}/variants/{variant}/generate-ean', [ProductController::class, 'generateEan'])
        ->middleware('permission:'.Permission::EDIT_PRODUCTS->value)->name('products.variants.generate-ean');
});

// ==========================================
// Placeholder Routes
// ==========================================
Route::middleware(['auth'])->group(function () {
    Route::get('/produto', fn () => redirect('/products'))->name('produto');
    Route::get('/planejamento', fn () => Inertia::render('ComingSoon', ['title' => 'Planejamento']))->name('planejamento');
    Route::get('/financeiro', fn () => Inertia::render('ComingSoon', ['title' => 'Financeiro']))->name('financeiro');
    Route::get('/ativo-fixo', fn () => Inertia::render('ComingSoon', ['title' => 'Ativo Fixo']))->name('ativo-fixo');
    Route::get('/comercial', fn () => redirect('/sales'))->name('comercial');
    // Deliveries — Admin (VIEW_DELIVERIES)
    Route::middleware(['tenant.module:delivery', 'permission:'.Permission::VIEW_DELIVERIES->value])->group(function () {
        Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('/deliveries/statistics', [DeliveryController::class, 'statistics'])->name('deliveries.statistics');
        Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');

        Route::middleware('permission:'.Permission::CREATE_DELIVERIES->value)->group(function () {
            Route::post('/deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
        });

        Route::middleware('permission:'.Permission::EDIT_DELIVERIES->value)->group(function () {
            Route::put('/deliveries/{delivery}', [DeliveryController::class, 'update'])->name('deliveries.update');
            Route::post('/deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus'])->name('deliveries.status');
        });

        Route::middleware('permission:'.Permission::DELETE_DELIVERIES->value)->group(function () {
            Route::delete('/deliveries/{delivery}', [DeliveryController::class, 'destroy'])->name('deliveries.destroy');
        });

        // Delivery Routes — Admin
        Route::get('/delivery-routes', [DeliveryRouteController::class, 'index'])->name('delivery-routes.index');
        Route::get('/delivery-routes/statistics', [DeliveryRouteController::class, 'statistics'])->name('delivery-routes.statistics');
        Route::get('/delivery-routes/{deliveryRoute}', [DeliveryRouteController::class, 'show'])->name('delivery-routes.show');
        Route::get('/delivery-routes/{deliveryRoute}/print', [DeliveryRouteController::class, 'printManifest'])->name('delivery-routes.print');

        Route::middleware('permission:'.Permission::MANAGE_ROUTES->value)->group(function () {
            Route::post('/delivery-routes', [DeliveryRouteController::class, 'store'])->name('delivery-routes.store');
            Route::post('/delivery-routes/optimize-preview', [DeliveryRouteController::class, 'optimizePreview'])->name('delivery-routes.optimize-preview');
            Route::put('/delivery-routes/{deliveryRoute}', [DeliveryRouteController::class, 'update'])->name('delivery-routes.update');
            Route::post('/delivery-routes/{deliveryRoute}/start', [DeliveryRouteController::class, 'startRoute'])->name('delivery-routes.start');
            Route::post('/delivery-routes/{deliveryRoute}/optimize', [DeliveryRouteController::class, 'optimizeRoute'])->name('delivery-routes.optimize');
            Route::post('/delivery-routes/{deliveryRoute}/items/{item}/complete', [DeliveryRouteController::class, 'completeItem'])->name('delivery-routes.complete-item');
            Route::post('/delivery-routes/{deliveryRoute}/cancel', [DeliveryRouteController::class, 'cancel'])->name('delivery-routes.cancel');

            // Route Templates
            Route::get('/delivery-route-templates', [DeliveryRouteController::class, 'listTemplates'])->name('delivery-route-templates.index');
            Route::get('/delivery-route-templates/{template}', [DeliveryRouteController::class, 'showTemplate'])->name('delivery-route-templates.show');
            Route::post('/delivery-routes/{deliveryRoute}/save-template', [DeliveryRouteController::class, 'saveAsTemplate'])->name('delivery-routes.save-template');
            Route::post('/delivery-route-templates/{template}/create-route', [DeliveryRouteController::class, 'createFromTemplate'])->name('delivery-route-templates.create-route');
            Route::delete('/delivery-route-templates/{template}', [DeliveryRouteController::class, 'deleteTemplate'])->name('delivery-route-templates.destroy');
        });
    });

    // Driver pages — VIEW_DELIVERIES (motorista acessa seu painel, completa entregas, envia GPS)
    Route::middleware(['tenant.module:delivery', 'permission:'.Permission::VIEW_DELIVERIES->value])->group(function () {
        Route::get('/driver-dashboard', [DeliveryRouteController::class, 'driverDashboard'])->name('driver-dashboard.index');
        Route::get('/my-deliveries', [DeliveryRouteController::class, 'myDeliveries'])->name('my-deliveries.index');
        Route::post('/driver-location', [DeliveryRouteController::class, 'storeDriverLocation'])->name('driver-location.store');
        Route::post('/driver-routes/{deliveryRoute}/items/{item}/complete', [DeliveryRouteController::class, 'completeItem'])->name('driver-routes.complete-item');
    });

    // GPS Tracking — VIEW_DELIVERIES (admin pode ver localização do motorista)
    Route::middleware(['tenant.module:delivery', 'permission:'.Permission::VIEW_DELIVERIES->value])->group(function () {
        Route::get('/delivery-routes/{deliveryRoute}/tracking', [DeliveryRouteController::class, 'getRouteTracking'])->name('delivery-routes.tracking');
    });

    // ==========================================
    // User Sessions
    // ==========================================
    Route::middleware(['tenant.module:user_sessions', 'permission:'.Permission::VIEW_USER_SESSIONS->value])->group(function () {
        Route::get('/user-sessions', [UserSessionController::class, 'index'])->name('user-sessions.index');
    });
    Route::post('/user-sessions/heartbeat', [UserSessionController::class, 'heartbeat'])->name('user-sessions.heartbeat');

    // ==========================================
    // Transfers
    // ==========================================
    Route::middleware(['tenant.module:transfers', 'permission:'.Permission::VIEW_TRANSFERS->value])->group(function () {
        Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('/transfers/statistics', [TransferController::class, 'statistics'])->name('transfers.statistics');
        Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
        Route::middleware('permission:'.Permission::CREATE_TRANSFERS->value)->group(function () {
            Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
        });
        Route::middleware('permission:'.Permission::EDIT_TRANSFERS->value)->group(function () {
            Route::put('/transfers/{transfer}', [TransferController::class, 'update'])->name('transfers.update');
            Route::post('/transfers/{transfer}/confirm-pickup', [TransferController::class, 'confirmPickup'])->name('transfers.confirm-pickup');
            Route::post('/transfers/{transfer}/confirm-delivery', [TransferController::class, 'confirmDelivery'])->name('transfers.confirm-delivery');
            Route::post('/transfers/{transfer}/confirm-receipt', [TransferController::class, 'confirmReceipt'])->name('transfers.confirm-receipt');
            Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
        });
        Route::middleware('permission:'.Permission::DELETE_TRANSFERS->value)->group(function () {
            Route::delete('/transfers/{transfer}', [TransferController::class, 'destroy'])->name('transfers.destroy');
        });
    });

    // ==========================================
    // Stock Adjustments
    // ==========================================
    Route::middleware(['tenant.module:stock_adjustments', 'permission:'.Permission::VIEW_ADJUSTMENTS->value])->group(function () {
        Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
        Route::get('/stock-adjustments/export', [StockAdjustmentController::class, 'export'])->name('stock-adjustments.export');
        // Lookups para o formulário (precisam vir antes do {stockAdjustment})
        Route::get('/stock-adjustments/lookup/employees', [StockAdjustmentController::class, 'employeesByStore'])
            ->name('stock-adjustments.lookup.employees');
        Route::get('/stock-adjustments/lookup/products', [StockAdjustmentController::class, 'searchProducts'])
            ->name('stock-adjustments.lookup.products');
        Route::get('/stock-adjustments/lookup/products/{reference}/sizes', [StockAdjustmentController::class, 'productSizes'])
            ->name('stock-adjustments.lookup.product-sizes');
        Route::get('/stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'show'])->name('stock-adjustments.show');
        Route::get('/stock-adjustments/{stockAdjustment}/attachments/{attachment}/download', [StockAdjustmentController::class, 'downloadAttachment'])
            ->name('stock-adjustments.attachments.download');

        Route::middleware('permission:'.Permission::CREATE_ADJUSTMENTS->value)->group(function () {
            Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
            Route::post('/stock-adjustments/{stockAdjustment}/attachments', [StockAdjustmentController::class, 'storeAttachment'])
                ->name('stock-adjustments.attachments.store');
        });

        Route::middleware('permission:'.Permission::EDIT_ADJUSTMENTS->value)->group(function () {
            Route::put('/stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'update'])->name('stock-adjustments.update');
            Route::post('/stock-adjustments/{stockAdjustment}/transition', [StockAdjustmentController::class, 'transition'])->name('stock-adjustments.transition');
            Route::post('/stock-adjustments/bulk-transition', [StockAdjustmentController::class, 'bulkTransition'])->name('stock-adjustments.bulk-transition');
            Route::post('/stock-adjustments/{stockAdjustment}/nfs', [StockAdjustmentController::class, 'storeNf'])->name('stock-adjustments.nfs.store');
            Route::delete('/stock-adjustments/{stockAdjustment}/nfs/{nf}', [StockAdjustmentController::class, 'destroyNf'])->name('stock-adjustments.nfs.destroy');
        });

        Route::middleware('permission:'.Permission::DELETE_ADJUSTMENTS->value)->group(function () {
            Route::delete('/stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'destroy'])->name('stock-adjustments.destroy');
            Route::delete('/stock-adjustments/{stockAdjustment}/attachments/{attachment}', [StockAdjustmentController::class, 'destroyAttachment'])
                ->name('stock-adjustments.attachments.destroy');
        });
    });

    // ==========================================
    // Order Payments
    // ==========================================
    Route::middleware(['tenant.module:order_payments', 'permission:'.Permission::VIEW_ORDER_PAYMENTS->value])->group(function () {
        Route::get('/order-payments', [OrderPaymentController::class, 'index'])->name('order-payments.index');
        Route::get('/order-payments/statistics', [OrderPaymentController::class, 'statistics'])->name('order-payments.statistics');
        Route::get('/order-payments/dashboard', [OrderPaymentController::class, 'dashboard'])->name('order-payments.dashboard');
        Route::get('/order-payments/export', [OrderPaymentController::class, 'export'])->name('order-payments.export');
        Route::get('/order-payments/{orderPayment}', [OrderPaymentController::class, 'show'])->name('order-payments.show');
        Route::get('/order-payments/{orderPayment}/delete-check', [OrderPaymentController::class, 'checkDeletePermission'])->name('order-payments.delete-check');
        Route::middleware('permission:'.Permission::CREATE_ORDER_PAYMENTS->value)->group(function () {
            Route::post('/order-payments', [OrderPaymentController::class, 'store'])->name('order-payments.store');
        });
        Route::middleware('permission:'.Permission::EDIT_ORDER_PAYMENTS->value)->group(function () {
            Route::put('/order-payments/{orderPayment}', [OrderPaymentController::class, 'update'])->name('order-payments.update');
            Route::post('/order-payments/{orderPayment}/transition', [OrderPaymentController::class, 'transition'])->name('order-payments.transition');
            Route::post('/order-payments/bulk-transition', [OrderPaymentController::class, 'bulkTransition'])->name('order-payments.bulk-transition');
            Route::post('/order-payments/{orderPayment}/allocations', [OrderPaymentController::class, 'saveAllocations'])->name('order-payments.allocations');
            Route::post('/order-payments/{orderPayment}/restore', [OrderPaymentController::class, 'restore'])->name('order-payments.restore');
            Route::post('/order-payment-installments/{installment}/mark-paid', [OrderPaymentController::class, 'markInstallmentPaid'])->name('order-payments.installment-mark-paid');
        });
        Route::middleware('permission:'.Permission::DELETE_ORDER_PAYMENTS->value)->group(function () {
            Route::delete('/order-payments/{orderPayment}', [OrderPaymentController::class, 'destroy'])->name('order-payments.destroy');
        });
    });

    // ==========================================
    // Suppliers
    // ==========================================
    Route::middleware(['tenant.module:suppliers', 'permission:'.Permission::VIEW_SUPPLIERS->value])->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::middleware('permission:'.Permission::CREATE_SUPPLIERS->value)->group(function () {
            Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        });
        Route::middleware('permission:'.Permission::EDIT_SUPPLIERS->value)->group(function () {
            Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        });
        Route::middleware('permission:'.Permission::DELETE_SUPPLIERS->value)->group(function () {
            Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
        });
    });

    // ==========================================
    // Férias (Vacations)
    // ==========================================
    Route::middleware(['tenant.module:vacations', 'permission:'.Permission::VIEW_VACATIONS->value])->group(function () {
        Route::get('/vacations', [VacationController::class, 'index'])->name('vacations.index');
        Route::get('/vacations/check-date', [VacationController::class, 'checkDate'])->name('vacations.check-date');
        Route::get('/vacations/holidays', [VacationController::class, 'holidays'])->name('vacations.holidays');
        Route::get('/vacations/{vacation}', [VacationController::class, 'show'])->name('vacations.show');
        Route::get('/employees/{employee}/vacation-balance', [VacationController::class, 'balance'])->name('vacations.balance');
        Route::middleware('permission:'.Permission::CREATE_VACATIONS->value)->group(function () {
            Route::post('/vacations', [VacationController::class, 'store'])->name('vacations.store');
            Route::post('/vacations/generate-periods', [VacationController::class, 'generatePeriods'])->name('vacations.generate-periods');
        });
        Route::middleware('permission:'.Permission::EDIT_VACATIONS->value)->group(function () {
            Route::put('/vacations/{vacation}', [VacationController::class, 'update'])->name('vacations.update');
            Route::post('/vacations/{vacation}/transition', [VacationController::class, 'transition'])->name('vacations.transition');
        });
        Route::middleware('permission:'.Permission::DELETE_VACATIONS->value)->group(function () {
            Route::delete('/vacations/{vacation}', [VacationController::class, 'destroy'])->name('vacations.destroy');
        });
        Route::middleware('permission:'.Permission::MANAGE_HOLIDAYS->value)->group(function () {
            Route::post('/vacations/holidays', [VacationController::class, 'storeHoliday'])->name('vacations.holidays.store');
            Route::put('/vacations/holidays/{holiday}', [VacationController::class, 'updateHoliday'])->name('vacations.holidays.update');
            Route::delete('/vacations/holidays/{holiday}', [VacationController::class, 'destroyHoliday'])->name('vacations.holidays.destroy');
        });
    });

    // ==========================================
    // Medical Certificates
    // ==========================================
    Route::middleware(['tenant.module:medical_certificates', 'permission:'.Permission::VIEW_MEDICAL_CERTIFICATES->value])->group(function () {
        Route::get('/medical-certificates', [MedicalCertificateController::class, 'index'])->name('medical-certificates.index');
        Route::get('/medical-certificates/{medical_certificate}', [MedicalCertificateController::class, 'show'])->name('medical-certificates.show');
        Route::middleware('permission:'.Permission::CREATE_MEDICAL_CERTIFICATES->value)->group(function () {
            Route::post('/medical-certificates', [MedicalCertificateController::class, 'store'])->name('medical-certificates.store');
        });
        Route::middleware('permission:'.Permission::EDIT_MEDICAL_CERTIFICATES->value)->group(function () {
            Route::put('/medical-certificates/{medical_certificate}', [MedicalCertificateController::class, 'update'])->name('medical-certificates.update');
        });
        Route::middleware('permission:'.Permission::DELETE_MEDICAL_CERTIFICATES->value)->group(function () {
            Route::delete('/medical-certificates/{medical_certificate}', [MedicalCertificateController::class, 'destroy'])->name('medical-certificates.destroy');
        });
    });

    // ==========================================
    // Absences
    // ==========================================
    Route::middleware(['tenant.module:absences', 'permission:'.Permission::VIEW_ABSENCES->value])->group(function () {
        Route::get('/absences', [AbsenceController::class, 'index'])->name('absences.index');
        Route::get('/absences/{absence}', [AbsenceController::class, 'show'])->name('absences.show');
        Route::middleware('permission:'.Permission::CREATE_ABSENCES->value)->group(function () {
            Route::post('/absences', [AbsenceController::class, 'store'])->name('absences.store');
        });
        Route::middleware('permission:'.Permission::EDIT_ABSENCES->value)->group(function () {
            Route::put('/absences/{absence}', [AbsenceController::class, 'update'])->name('absences.update');
        });
        Route::middleware('permission:'.Permission::DELETE_ABSENCES->value)->group(function () {
            Route::delete('/absences/{absence}', [AbsenceController::class, 'destroy'])->name('absences.destroy');
        });
    });

    // ==========================================
    // Overtime Records
    // ==========================================
    Route::middleware(['tenant.module:overtime', 'permission:'.Permission::VIEW_OVERTIME->value])->group(function () {
        Route::get('/overtime-records', [OvertimeRecordController::class, 'index'])->name('overtime-records.index');
        Route::get('/overtime-records/{overtime_record}', [OvertimeRecordController::class, 'show'])->name('overtime-records.show');
        Route::middleware('permission:'.Permission::CREATE_OVERTIME->value)->group(function () {
            Route::post('/overtime-records', [OvertimeRecordController::class, 'store'])->name('overtime-records.store');
        });
        Route::middleware('permission:'.Permission::EDIT_OVERTIME->value)->group(function () {
            Route::put('/overtime-records/{overtime_record}', [OvertimeRecordController::class, 'update'])->name('overtime-records.update');
        });
        Route::middleware('permission:'.Permission::DELETE_OVERTIME->value)->group(function () {
            Route::delete('/overtime-records/{overtime_record}', [OvertimeRecordController::class, 'destroy'])->name('overtime-records.destroy');
        });
    });

    // ==========================================
    // Checklists
    // ==========================================
    Route::middleware(['tenant.module:checklists', 'permission:'.Permission::VIEW_CHECKLISTS->value])->group(function () {
        Route::get('/checklists', [ChecklistController::class, 'index'])->name('checklists.index');
        Route::get('/checklists/employees', [ChecklistController::class, 'employees'])->name('checklists.employees');
        Route::get('/checklists/{checklist}', [ChecklistController::class, 'show'])->name('checklists.show');
        Route::get('/checklists/{checklist}/statistics', [ChecklistController::class, 'statistics'])->name('checklists.statistics');
        Route::middleware('permission:'.Permission::CREATE_CHECKLISTS->value)->group(function () {
            Route::post('/checklists', [ChecklistController::class, 'store'])->name('checklists.store');
        });
        Route::middleware('permission:'.Permission::EDIT_CHECKLISTS->value)->group(function () {
            Route::put('/checklists/{checklist}/answers/{answer}', [ChecklistController::class, 'updateAnswer'])->name('checklists.update-answer');
        });
        Route::middleware('permission:'.Permission::DELETE_CHECKLISTS->value)->group(function () {
            Route::delete('/checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklists.destroy');
        });
    });

    // ==========================================
    // Integrations
    // ==========================================
    Route::middleware(['tenant.module:integrations', 'permission:'.Permission::MANAGE_SETTINGS->value])->prefix('integrations')->name('integrations.')->group(function () {
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
    // Stock Audits (Auditoria de Estoque)
    // ==========================================
    Route::middleware(['tenant.module:stock_audits', 'permission:'.Permission::VIEW_STOCK_AUDITS->value])->group(function () {
        Route::get('/stock-audits/statistics', [StockAuditController::class, 'statistics'])->name('stock-audits.statistics');
        Route::get('/stock-audits/accuracy-history', [StockAuditController::class, 'accuracyHistory'])->name('stock-audits.accuracy-history');
        Route::get('/stock-audits/create-options', [StockAuditController::class, 'createOptions'])->name('stock-audits.create-options');
        Route::get('/stock-audits', [StockAuditController::class, 'index'])->name('stock-audits.index');
        Route::get('/stock-audits/{stockAudit}', [StockAuditController::class, 'show'])->name('stock-audits.show');
        Route::get('/stock-audits/{stockAudit}/report', [StockAuditController::class, 'report'])->name('stock-audits.report');
        Route::get('/stock-audits/{stockAudit}/pendencies', [StockAuditController::class, 'pendencies'])->name('stock-audits.pendencies');

        Route::middleware('permission:'.Permission::CREATE_STOCK_AUDITS->value)->group(function () {
            Route::post('/stock-audits', [StockAuditController::class, 'store'])->name('stock-audits.store');
        });

        Route::middleware('permission:'.Permission::EDIT_STOCK_AUDITS->value)->group(function () {
            Route::put('/stock-audits/{stockAudit}', [StockAuditController::class, 'update'])->name('stock-audits.update');
            Route::post('/stock-audits/{stockAudit}/transition', [StockAuditController::class, 'transition'])->name('stock-audits.transition');
            Route::post('/stock-audits/{stockAudit}/areas', [StockAuditController::class, 'areas'])->name('stock-audits.areas');
            Route::post('/stock-audits/{stockAudit}/teams', [StockAuditController::class, 'teams'])->name('stock-audits.teams');
            Route::post('/stock-audits/{stockAudit}/sign', [StockAuditController::class, 'sign'])->name('stock-audits.sign');
            Route::post('/stock-audits/{stockAudit}/justify', [StockAuditController::class, 'submitJustification'])->name('stock-audits.justify');
        });

        Route::middleware('permission:'.Permission::COUNT_STOCK_AUDITS->value)->group(function () {
            Route::get('/stock-audits/{stockAudit}/counting', [StockAuditController::class, 'counting'])->name('stock-audits.counting');
            Route::post('/stock-audits/{stockAudit}/count', [StockAuditController::class, 'count'])->name('stock-audits.count');
            Route::post('/stock-audits/{stockAudit}/clear-count', [StockAuditController::class, 'clearCount'])->name('stock-audits.clear-count');
            Route::post('/stock-audits/{stockAudit}/finalize-round', [StockAuditController::class, 'finalizeRound'])->name('stock-audits.finalize-round');
            Route::post('/stock-audits/{stockAudit}/import', [StockAuditController::class, 'importCount'])->name('stock-audits.import');
        });

        Route::middleware('permission:'.Permission::RECONCILE_STOCK_AUDITS->value)->group(function () {
            Route::get('/stock-audits/{stockAudit}/reconciliation', [StockAuditController::class, 'reconciliation'])->name('stock-audits.reconciliation');
            Route::post('/stock-audits/{stockAudit}/reconcile-a', [StockAuditController::class, 'reconcilePhaseA'])->name('stock-audits.reconcile-a');
            Route::post('/stock-audits/{stockAudit}/reconcile-b', [StockAuditController::class, 'reconcilePhaseB'])->name('stock-audits.reconcile-b');
            Route::post('/stock-audits/{stockAudit}/review-justification', [StockAuditController::class, 'reviewJustification'])->name('stock-audits.review-justification');
        });

        Route::middleware('permission:'.Permission::DELETE_STOCK_AUDITS->value)->group(function () {
            Route::delete('/stock-audits/{stockAudit}', [StockAuditController::class, 'destroy'])->name('stock-audits.destroy');
        });
    });

    // Stock Audit Config (Ciclos e Empresas Auditoras)
    Route::middleware(['tenant.module:stock_audits', 'permission:'.Permission::MANAGE_STOCK_AUDIT_CONFIG->value])->group(function () {
        Route::get('/config/stock-audit-cycles', [ConfigStockAuditCycleController::class, 'index'])->name('config.stock-audit-cycles.index');
        Route::post('/config/stock-audit-cycles', [ConfigStockAuditCycleController::class, 'store'])->name('config.stock-audit-cycles.store');
        Route::put('/config/stock-audit-cycles/{stockAuditCycle}', [ConfigStockAuditCycleController::class, 'update'])->name('config.stock-audit-cycles.update');
        Route::delete('/config/stock-audit-cycles/{stockAuditCycle}', [ConfigStockAuditCycleController::class, 'destroy'])->name('config.stock-audit-cycles.destroy');
        Route::get('/config/stock-audit-vendors', [ConfigStockAuditVendorController::class, 'index'])->name('config.stock-audit-vendors.index');
        Route::post('/config/stock-audit-vendors', [ConfigStockAuditVendorController::class, 'store'])->name('config.stock-audit-vendors.store');
        Route::put('/config/stock-audit-vendors/{stockAuditVendor}', [ConfigStockAuditVendorController::class, 'update'])->name('config.stock-audit-vendors.update');
        Route::delete('/config/stock-audit-vendors/{stockAuditVendor}', [ConfigStockAuditVendorController::class, 'destroy'])->name('config.stock-audit-vendors.destroy');
    });

    // ==========================================
    // Personnel Movements (Movimentação de Pessoal)
    // ==========================================
    Route::middleware(['tenant.module:personnel_movements', 'permission:'.Permission::VIEW_PERSONNEL_MOVEMENTS->value])->group(function () {
        Route::get('/personnel-movements', [PersonnelMovementController::class, 'index'])->name('personnel-movements.index');
        Route::get('/personnel-movements/{personnelMovement}', [PersonnelMovementController::class, 'show'])->name('personnel-movements.show');
        Route::get('/employees/{employee}/integration-data', [PersonnelMovementController::class, 'getEmployeeIntegrationData'])->name('personnel-movements.integration-data');

        Route::middleware('permission:'.Permission::CREATE_PERSONNEL_MOVEMENTS->value)->group(function () {
            Route::post('/personnel-movements', [PersonnelMovementController::class, 'store'])->name('personnel-movements.store');
            Route::post('/personnel-movements/{personnelMovement}/files', [PersonnelMovementController::class, 'uploadFile'])->name('personnel-movements.files.upload');
        });

        Route::middleware('permission:'.Permission::EDIT_PERSONNEL_MOVEMENTS->value)->group(function () {
            Route::get('/personnel-movements/{personnelMovement}/edit', [PersonnelMovementController::class, 'edit'])->name('personnel-movements.edit');
            Route::put('/personnel-movements/{personnelMovement}', [PersonnelMovementController::class, 'update'])->name('personnel-movements.update');
            Route::post('/personnel-movements/{personnelMovement}/transition', [PersonnelMovementController::class, 'transition'])->name('personnel-movements.transition');
            Route::put('/personnel-movements/{personnelMovement}/follow-up', [PersonnelMovementController::class, 'updateFollowUp'])->name('personnel-movements.follow-up.update');
            Route::delete('/personnel-movements/files/{file}', [PersonnelMovementController::class, 'deleteFile'])->name('personnel-movements.files.delete');
        });

        Route::middleware('permission:'.Permission::DELETE_PERSONNEL_MOVEMENTS->value)->group(function () {
            Route::delete('/personnel-movements/{personnelMovement}', [PersonnelMovementController::class, 'destroy'])->name('personnel-movements.destroy');
        });
    });

    // ==========================================
    // Trainings (Treinamentos)
    // ==========================================
    Route::middleware(['tenant.module:training', 'permission:'.Permission::VIEW_TRAININGS->value])->group(function () {
        Route::get('/trainings', [TrainingEventController::class, 'index'])->name('trainings.index');
        Route::get('/trainings/statistics', [TrainingEventController::class, 'statistics'])->name('trainings.statistics');
        Route::get('/trainings/{training}', [TrainingEventController::class, 'show'])->name('trainings.show');
        Route::get('/trainings/{training}/edit', [TrainingEventController::class, 'edit'])->name('trainings.edit');
        Route::get('/trainings/{training}/qr-codes', [TrainingEventController::class, 'qrCodes'])->name('trainings.qr-codes');
        Route::get('/trainings/{training}/certificates/{participant}/download', [TrainingEventController::class, 'downloadCertificate'])->name('trainings.certificates.download');

        Route::middleware('permission:'.Permission::CREATE_TRAININGS->value)->group(function () {
            Route::post('/trainings', [TrainingEventController::class, 'store'])->name('trainings.store');
            Route::post('/training-facilitators', [TrainingEventController::class, 'storeFacilitator'])->name('trainings.facilitators.store');
            Route::post('/training-subjects', [TrainingEventController::class, 'storeSubject'])->name('trainings.subjects.store');
        });

        Route::middleware('permission:'.Permission::EDIT_TRAININGS->value)->group(function () {
            Route::put('/trainings/{training}', [TrainingEventController::class, 'update'])->name('trainings.update');
            Route::post('/trainings/{training}/transition', [TrainingEventController::class, 'transition'])->name('trainings.transition');
            Route::post('/trainings/{training}/certificates/generate', [TrainingEventController::class, 'generateCertificates'])->name('trainings.certificates.generate');
            Route::put('/training-facilitators/{facilitator}', [TrainingEventController::class, 'updateFacilitator'])->name('trainings.facilitators.update');
            Route::put('/training-subjects/{subject}', [TrainingEventController::class, 'updateSubject'])->name('trainings.subjects.update');
        });

        Route::middleware('permission:'.Permission::MANAGE_TRAINING_ATTENDANCE->value)->group(function () {
            Route::post('/trainings/{training}/participants', [TrainingEventController::class, 'addParticipant'])->name('trainings.participants.store');
            Route::delete('/trainings/{training}/participants/{participant}', [TrainingEventController::class, 'removeParticipant'])->name('trainings.participants.destroy');
            Route::post('/trainings/{training}/participants/{participant}/evaluation', [TrainingEventController::class, 'submitEvaluation'])->name('trainings.evaluations.store');
        });

        Route::middleware('permission:'.Permission::DELETE_TRAININGS->value)->group(function () {
            Route::delete('/trainings/{training}', [TrainingEventController::class, 'destroy'])->name('trainings.destroy');
        });

        // Training Content (within same module)
        Route::get('/training-contents', [TrainingContentController::class, 'index'])->name('training-contents.index');
        Route::get('/training-contents/{trainingContent}', [TrainingContentController::class, 'show'])->name('training-contents.show');

        Route::middleware('permission:'.Permission::MANAGE_TRAINING_CONTENT->value)->group(function () {
            Route::post('/training-contents', [TrainingContentController::class, 'store'])->name('training-contents.store');
            Route::put('/training-contents/{trainingContent}', [TrainingContentController::class, 'update'])->name('training-contents.update');
            Route::delete('/training-contents/{trainingContent}', [TrainingContentController::class, 'destroy'])->name('training-contents.destroy');
            Route::post('/training-content-categories', [TrainingContentController::class, 'storeCategory'])->name('training-content-categories.store');
            Route::put('/training-content-categories/{category}', [TrainingContentController::class, 'updateCategory'])->name('training-content-categories.update');
        });

        // Training Courses
        Route::middleware('permission:'.Permission::VIEW_TRAINING_COURSES->value)->group(function () {
            Route::get('/training-courses', [TrainingCourseController::class, 'index'])->name('training-courses.index');
            Route::get('/training-courses/{trainingCourse}', [TrainingCourseController::class, 'show'])->name('training-courses.show');
            Route::get('/my-trainings', [TrainingCourseController::class, 'myTrainings'])->name('my-trainings.index');
            Route::get('/training-courses/{trainingCourse}/start', [TrainingCourseController::class, 'startCourse'])->name('training-courses.start');
            Route::get('/training-courses/{trainingCourse}/watch/{content}', [TrainingCourseController::class, 'watchContent'])->name('training-courses.watch');
            Route::post('/training-courses/{trainingCourse}/enroll', [TrainingCourseController::class, 'enroll'])->name('training-courses.enroll');
            Route::get('/training-courses/{trainingCourse}/certificate', [TrainingCourseController::class, 'downloadCertificate'])->name('training-courses.certificate');
            Route::post('/training-courses/{trainingCourse}/certificate/regenerate', [TrainingCourseController::class, 'regenerateCertificate'])->name('training-courses.certificate.regenerate');
            Route::post('/training-contents/{content}/progress', [TrainingCourseController::class, 'saveProgress'])->name('training-contents.progress');
            Route::post('/training-contents/{content}/complete', [TrainingCourseController::class, 'markComplete'])->name('training-contents.complete');
            Route::get('/training-contents/stream/{path}', [TrainingCourseController::class, 'streamFile'])->where('path', '.*')->name('training-contents.stream');
            Route::get('/training-reports', [TrainingCourseController::class, 'reports'])->name('training-reports.index');
            Route::get('/training-reports/export', [TrainingCourseController::class, 'exportReport'])->name('training-reports.export');
        });

        Route::middleware('permission:'.Permission::CREATE_TRAINING_COURSES->value)->group(function () {
            Route::post('/training-courses', [TrainingCourseController::class, 'store'])->name('training-courses.store');
        });

        Route::middleware('permission:'.Permission::EDIT_TRAINING_COURSES->value)->group(function () {
            Route::put('/training-courses/{trainingCourse}', [TrainingCourseController::class, 'update'])->name('training-courses.update');
            Route::post('/training-courses/{trainingCourse}/transition', [TrainingCourseController::class, 'transition'])->name('training-courses.transition');
            Route::post('/training-courses/{trainingCourse}/contents', [TrainingCourseController::class, 'manageContents'])->name('training-courses.contents');
            Route::post('/training-courses/{trainingCourse}/visibility', [TrainingCourseController::class, 'manageVisibility'])->name('training-courses.visibility');
        });

        Route::middleware('permission:'.Permission::DELETE_TRAINING_COURSES->value)->group(function () {
            Route::delete('/training-courses/{trainingCourse}', [TrainingCourseController::class, 'destroy'])->name('training-courses.destroy');
        });

        // Training Quizzes
        Route::get('/training-quizzes', [TrainingQuizController::class, 'index'])->name('training-quizzes.index');
        Route::get('/training-quizzes/{trainingQuiz}', [TrainingQuizController::class, 'show'])->name('training-quizzes.show');
        Route::get('/training-quizzes/{trainingQuiz}/take', [TrainingQuizController::class, 'take'])->name('training-quizzes.take');
        Route::post('/training-quizzes/{trainingQuiz}/start', [TrainingQuizController::class, 'start'])->name('training-quizzes.start');
        Route::post('/training-quiz-attempts/{attempt}/submit', [TrainingQuizController::class, 'submit'])->name('training-quiz-attempts.submit');
        Route::get('/training-quiz-attempts/{attempt}/review', [TrainingQuizController::class, 'review'])->name('training-quiz-attempts.review');

        Route::middleware('permission:'.Permission::MANAGE_TRAINING_QUIZZES->value)->group(function () {
            Route::post('/training-quizzes', [TrainingQuizController::class, 'store'])->name('training-quizzes.store');
            Route::put('/training-quizzes/{trainingQuiz}', [TrainingQuizController::class, 'update'])->name('training-quizzes.update');
            Route::delete('/training-quizzes/{trainingQuiz}', [TrainingQuizController::class, 'destroy'])->name('training-quizzes.destroy');
            Route::get('/training-quizzes/{trainingQuiz}/ungraded', [TrainingQuizController::class, 'ungradedResponses'])->name('training-quizzes.ungraded');
            Route::put('/training-quiz-responses/{response}/grade', [TrainingQuizController::class, 'gradeResponse'])->name('training-quiz-responses.grade');
        });
    });

    // ==========================================
    // Experience Tracker (Avaliacao de Experiencia)
    // ==========================================
    Route::middleware(['tenant.module:experience-tracker', 'permission:'.Permission::VIEW_EXPERIENCE_TRACKER->value])->group(function () {
        Route::get('/experience-tracker', [ExperienceTrackerController::class, 'index'])->name('experience-tracker.index');
        Route::get('/experience-tracker/statistics', [ExperienceTrackerController::class, 'statistics'])->name('experience-tracker.statistics');
        Route::get('/experience-tracker/compliance', [ExperienceTrackerController::class, 'compliance'])->name('experience-tracker.compliance');
        Route::get('/experience-tracker/evolution', [ExperienceTrackerController::class, 'evolution'])->name('experience-tracker.evolution');
        Route::get('/experience-tracker/{experienceTracker}', [ExperienceTrackerController::class, 'show'])->name('experience-tracker.show');

        Route::middleware('permission:'.Permission::MANAGE_EXPERIENCE_TRACKER->value)->group(function () {
            Route::post('/experience-tracker', [ExperienceTrackerController::class, 'store'])->name('experience-tracker.store');
        });

        Route::middleware('permission:'.Permission::FILL_EXPERIENCE_EVALUATION->value)->group(function () {
            Route::post('/experience-tracker/{experienceTracker}/manager', [ExperienceTrackerController::class, 'fillManager'])->name('experience-tracker.fill-manager');
        });
    });

    // ==========================================
    // Chat
    // ==========================================
    Route::middleware(['tenant.module:chat', 'permission:'.Permission::VIEW_CHAT->value])->group(function () {
        Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
        Route::get('/chat/conversations/{conversation}', [ChatController::class, 'show'])->name('chat.show');
        Route::get('/chat/conversations.json', [ChatController::class, 'conversationsJson'])->name('chat.conversations-json');
        Route::get('/chat/conversations/{conversation}/messages', [ChatController::class, 'loadMessages'])->name('chat.load-messages');
        Route::get('/chat/messages/{message}/attachment', [ChatController::class, 'downloadAttachment'])->name('chat.download-attachment');
        Route::get('/chat/search', [ChatController::class, 'search'])->name('chat.search');
        Route::get('/chat/unread-counts', [ChatController::class, 'unreadCounts'])->name('chat.unread-counts');

        // Broadcasts (view)
        Route::get('/chat/broadcasts', [ChatBroadcastController::class, 'index'])->name('chat.broadcasts.index');
        Route::post('/chat/broadcasts/{broadcast}/read', [ChatBroadcastController::class, 'markRead'])->name('chat.broadcasts.mark-read');

        Route::middleware('permission:'.Permission::SEND_CHAT_MESSAGES->value)->group(function () {
            Route::post('/chat/conversations/direct', [ChatController::class, 'createDirect'])->name('chat.create-direct');
            Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'sendMessage'])->name('chat.send-message');
            Route::delete('/chat/messages/{message}', [ChatController::class, 'deleteMessage'])->name('chat.delete-message');
            Route::patch('/chat/messages/{message}', [ChatController::class, 'editMessage'])->name('chat.edit-message');
            Route::post('/chat/conversations/{conversation}/read', [ChatController::class, 'markRead'])->name('chat.mark-read');
            Route::post('/chat/conversations/{conversation}/typing', [ChatController::class, 'typing'])->name('chat.typing');
            Route::post('/chat/upload', [ChatController::class, 'uploadFile'])->name('chat.upload');
        });

        Route::middleware('permission:'.Permission::CREATE_CHAT_GROUPS->value)->group(function () {
            Route::post('/chat/groups', [ChatGroupController::class, 'store'])->name('chat.groups.store');
        });

        Route::middleware('permission:'.Permission::MANAGE_CHAT_GROUPS->value)->group(function () {
            Route::put('/chat/groups/{chatGroup}', [ChatGroupController::class, 'update'])->name('chat.groups.update');
            Route::delete('/chat/groups/{chatGroup}', [ChatGroupController::class, 'destroy'])->name('chat.groups.destroy');
            Route::post('/chat/groups/{chatGroup}/members', [ChatGroupController::class, 'addMember'])->name('chat.groups.add-member');
            Route::delete('/chat/groups/{chatGroup}/members/{user}', [ChatGroupController::class, 'removeMember'])->name('chat.groups.remove-member');
            Route::patch('/chat/groups/{chatGroup}/members/{user}/role', [ChatGroupController::class, 'updateMemberRole'])->name('chat.groups.update-role');
        });

        Route::middleware('permission:'.Permission::SEND_BROADCASTS->value)->group(function () {
            Route::post('/chat/broadcasts', [ChatBroadcastController::class, 'store'])->name('chat.broadcasts.store');
        });

        Route::middleware('permission:'.Permission::MANAGE_BROADCASTS->value)->group(function () {
            Route::put('/chat/broadcasts/{broadcast}', [ChatBroadcastController::class, 'update'])->name('chat.broadcasts.update');
            Route::delete('/chat/broadcasts/{broadcast}', [ChatBroadcastController::class, 'destroy'])->name('chat.broadcasts.destroy');
        });
    });

    // ==========================================
    // Helpdesk
    // ==========================================
    Route::middleware(['tenant.module:helpdesk', 'permission:'.Permission::VIEW_HELPDESK->value])->group(function () {
        Route::get('/helpdesk', [HelpdeskController::class, 'index'])->name('helpdesk.index');
        Route::get('/helpdesk/statistics', [HelpdeskController::class, 'statistics'])->name('helpdesk.statistics');
        Route::get('/helpdesk/departments/{department}/categories', [HelpdeskController::class, 'categories'])->name('helpdesk.categories');
        Route::get('/helpdesk/departments/{department}/technicians', [HelpdeskController::class, 'technicians'])->name('helpdesk.technicians');
        Route::get('/helpdesk/attachments/{attachment}/download', [HelpdeskController::class, 'downloadAttachment'])->name('helpdesk.download-attachment');
        Route::get('/helpdesk/export/csv', [HelpdeskController::class, 'exportCsv'])->name('helpdesk.export.csv');
        Route::get('/helpdesk/export/xlsx', [HelpdeskController::class, 'exportXlsx'])->name('helpdesk.export.xlsx');
        Route::get('/helpdesk/export/pdf', [HelpdeskController::class, 'exportPdf'])->name('helpdesk.export.pdf');
        Route::get('/helpdesk/saved-views', [HelpdeskSavedViewController::class, 'index'])->name('helpdesk.saved-views.index');
        Route::post('/helpdesk/saved-views', [HelpdeskSavedViewController::class, 'store'])->name('helpdesk.saved-views.store');
        Route::put('/helpdesk/saved-views/{savedView}', [HelpdeskSavedViewController::class, 'update'])->name('helpdesk.saved-views.update');
        Route::delete('/helpdesk/saved-views/{savedView}', [HelpdeskSavedViewController::class, 'destroy'])->name('helpdesk.saved-views.destroy');
        Route::get('/helpdesk/{ticket}', [HelpdeskController::class, 'show'])->name('helpdesk.show');

        Route::middleware('permission:'.Permission::CREATE_TICKETS->value)->group(function () {
            Route::post('/helpdesk', [HelpdeskController::class, 'store'])->name('helpdesk.store');
            Route::post('/helpdesk/{ticket}/comments', [HelpdeskController::class, 'addComment'])->name('helpdesk.add-comment');
            Route::post('/helpdesk/{ticket}/attachments', [HelpdeskController::class, 'uploadAttachment'])->name('helpdesk.upload-attachment');
        });

        Route::middleware('permission:'.Permission::MANAGE_TICKETS->value)->group(function () {
            Route::delete('/helpdesk/{ticket}', [HelpdeskController::class, 'destroy'])->name('helpdesk.destroy');
            Route::post('/helpdesk/bulk', [HelpdeskController::class, 'bulkAction'])->name('helpdesk.bulk');
            Route::post('/helpdesk/{ticket}/transition', [HelpdeskController::class, 'transition'])->name('helpdesk.transition');
            Route::post('/helpdesk/{ticket}/assign', [HelpdeskController::class, 'assign'])->name('helpdesk.assign');
            Route::post('/helpdesk/{ticket}/priority', [HelpdeskController::class, 'changePriority'])->name('helpdesk.change-priority');
            Route::post('/helpdesk/{ticket}/merge', [HelpdeskController::class, 'merge'])->name('helpdesk.merge');
        });

        Route::middleware('permission:'.Permission::VIEW_HD_REPORTS->value)->group(function () {
            // Legacy route — redirects to the unified /helpdesk?tab=reports view.
            Route::get('/helpdesk-reports', fn () => redirect()->route('helpdesk.index', ['tab' => 'reports']))
                ->name('helpdesk-reports.index');
            // JSON endpoints kept for dynamic in-page updates.
            Route::get('/helpdesk-reports/volume', [HelpdeskReportController::class, 'volumeByDay'])->name('helpdesk-reports.volume');
            Route::get('/helpdesk-reports/sla', [HelpdeskReportController::class, 'slaCompliance'])->name('helpdesk-reports.sla');
        });

        // Admin CRUD: Departments & Categories (MANAGE_HD_DEPARTMENTS)
        Route::middleware('permission:'.Permission::MANAGE_HD_DEPARTMENTS->value)->prefix('config')->name('config.')->group(function () {
            Route::resource('hd-departments', ConfigHdDepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('hd-categories', ConfigHdCategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        });

        // Admin CRUD: Department-level permissions (MANAGE_HD_PERMISSIONS)
        Route::middleware('permission:'.Permission::MANAGE_HD_PERMISSIONS->value)->group(function () {
            Route::get('/helpdesk/admin/permissions', [HdPermissionController::class, 'index'])->name('helpdesk.permissions.index');
            Route::post('/helpdesk/admin/permissions', [HdPermissionController::class, 'store'])->name('helpdesk.permissions.store');
            Route::put('/helpdesk/admin/permissions/{department}/{user}', [HdPermissionController::class, 'update'])->name('helpdesk.permissions.update');
            Route::delete('/helpdesk/admin/permissions/{department}/{user}', [HdPermissionController::class, 'destroy'])->name('helpdesk.permissions.destroy');
        });
    });

    // ==========================================
    // Placeholder Coming Soon
    // ==========================================
    Route::get('/ecommerce', fn () => Inertia::render('ComingSoon', ['title' => 'E-commerce']))->name('ecommerce');
    Route::get('/pessoas-cultura', fn () => Inertia::render('ComingSoon', ['title' => 'Pessoas & Cultura']))->name('pessoas-cultura');
    Route::get('/departamento-pessoal', fn () => Inertia::render('ComingSoon', ['title' => 'Departamento Pessoal']))->name('departamento-pessoal');
    Route::get('/biblioteca-processos', fn () => Inertia::render('ComingSoon', ['title' => 'Biblioteca de Processos']))->name('biblioteca-processos');
});

// ==========================================
// Public Routes (no auth required)
// ==========================================
Route::get('/public/experience/{token}', [ExperienceTrackerController::class, 'publicForm'])->name('experience-tracker.public-form');
Route::post('/public/experience/{token}', [ExperienceTrackerController::class, 'publicSubmit'])->name('experience-tracker.public-submit');

// Public Course Catalog
Route::get('/cursos', [PublicCourseController::class, 'catalog'])->name('public.courses.catalog');
Route::post('/cursos/{trainingCourse}/inscrever', [PublicCourseController::class, 'enroll'])->name('public.courses.enroll');

// Google OAuth cross-domain login (receives signed token from central domain)
Route::get('/auth/google-login', [PublicCourseController::class, 'googleLogin'])->name('auth.google.login');
