<?php

use App\Http\Controllers\ProfileController;
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
use App\Http\Controllers\PageGroupController;
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
use App\Enums\Permission;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'permission:' . Permission::ACCESS_DASHBOARD->value])
    ->name('dashboard');

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

// Rotas com controle de acesso por permissão
Route::middleware(['auth', 'permission:' . Permission::ACCESS_ADMIN_PANEL->value])->group(function () {
    Route::get('/admin', function () {
        return Inertia::render('Admin');
    })->name('admin');

    // Configurações de E-mail
    Route::middleware('permission:' . Permission::MANAGE_SETTINGS->value)->group(function () {
        Route::get('/admin/email-settings', [EmailSettingsController::class, 'index'])->name('admin.email-settings');
    });
});

Route::middleware(['auth', 'permission:' . Permission::ACCESS_SUPPORT_PANEL->value])->group(function () {
    Route::get('/support', function () {
        return Inertia::render('Support');
    })->name('support');
});

// Rotas para gerenciamento de usuários
Route::middleware('auth')->group(function () {
    // Visualizar usuários
    Route::get('/users', [UserManagementController::class, 'index'])
        ->middleware('permission:' . Permission::VIEW_USERS->value)
        ->name('users.index');

    // Criar usuários
    Route::get('/users/create', [UserManagementController::class, 'create'])
        ->middleware('permission:' . Permission::CREATE_USERS->value)
        ->name('users.create');
    Route::post('/users', [UserManagementController::class, 'store'])
        ->middleware('permission:' . Permission::CREATE_USERS->value)
        ->name('users.store');

    // Visualizar usuário específico
    Route::get('/users/{user}', [UserManagementController::class, 'show'])
        ->middleware('permission:' . Permission::VIEW_USERS->value . ',' . Permission::VIEW_ANY_PROFILE->value)
        ->name('users.show');

    // Editar usuários
    Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])
        ->middleware('permission:' . Permission::EDIT_USERS->value . ',' . Permission::EDIT_ANY_PROFILE->value)
        ->name('users.edit');
    Route::match(['put', 'post'], '/users/{user}', [UserManagementController::class, 'update'])
        ->middleware('permission:' . Permission::EDIT_USERS->value . ',' . Permission::EDIT_ANY_PROFILE->value)
        ->name('users.update');

    // Deletar usuários
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
        ->middleware('permission:' . Permission::DELETE_USERS->value)
        ->name('users.destroy');

    // Gerenciar roles
    Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole'])
        ->middleware('permission:' . Permission::MANAGE_USER_ROLES->value)
        ->name('users.updateRole');

    // Remover avatar
    Route::delete('/users/{user}/avatar', [UserManagementController::class, 'removeAvatar'])
        ->middleware('permission:' . Permission::EDIT_USERS->value . ',' . Permission::EDIT_ANY_PROFILE->value)
        ->name('users.removeAvatar');
});

// Rotas para logs de atividade
Route::middleware(['auth', 'permission:' . Permission::VIEW_ACTIVITY_LOGS->value])->group(function () {
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-logs/{log}', [ActivityLogController::class, 'show'])->name('activity-logs.show');

    // Exportar logs (apenas admins)
    Route::post('/activity-logs/export', [ActivityLogController::class, 'export'])
        ->middleware('permission:' . Permission::EXPORT_ACTIVITY_LOGS->value)
        ->name('activity-logs.export');

    // Limpeza de logs (apenas super admin)
    Route::delete('/activity-logs/cleanup', [ActivityLogController::class, 'cleanup'])
        ->middleware('permission:' . Permission::MANAGE_SYSTEM_SETTINGS->value)
        ->name('activity-logs.cleanup');
});

// Rotas para gerenciamento de menus
// API para sidebar (acessível para qualquer usuário autenticado)
Route::middleware('auth')->get('/api/menus/sidebar', [MenuController::class, 'getSidebarMenus'])->name('menus.sidebar');
Route::middleware('auth')->get('/api/menus/dynamic-sidebar', [MenuController::class, 'getDynamicSidebarMenus'])->name('menus.dynamic-sidebar');

Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
    // Listar menus
    Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');

    // Visualizar menu específico
    Route::get('/menus/{menu}', [MenuController::class, 'show'])->name('menus.show');

    // Ações de gerenciamento de menu (requer permissões administrativas)
    Route::middleware('permission:' . Permission::MANAGE_SYSTEM_SETTINGS->value)->group(function () {
        // Criar novo menu
        Route::post('/menus', [MenuController::class, 'store'])->name('menus.store');

        // Atualizar menu
        Route::put('/menus/{menu}', [MenuController::class, 'update'])->name('menus.update');

        // Ativar/Desativar menu
        Route::post('/menus/{menu}/activate', [MenuController::class, 'activate'])->name('menus.activate');
        Route::post('/menus/{menu}/deactivate', [MenuController::class, 'deactivate'])->name('menus.deactivate');

        // Mover menu para cima/baixo
        Route::post('/menus/{menu}/move-up', [MenuController::class, 'moveUp'])->name('menus.moveUp');
        Route::post('/menus/{menu}/move-down', [MenuController::class, 'moveDown'])->name('menus.moveDown');

        // Reordenar menus
        Route::post('/menus/reorder', [MenuController::class, 'reorder'])->name('menus.reorder');

        // Excluir menu
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');
    });
});

// Rotas para gerenciamento de páginas
Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
    // Listar páginas
    Route::get('/pages', [PageController::class, 'index'])->name('pages.index');

    // Criar nova página
    Route::post('/pages', [PageController::class, 'store'])->name('pages.store');

    // Visualizar página específica
    Route::get('/pages/{page}', [PageController::class, 'show'])->name('pages.show');

    // Atualizar página
    Route::patch('/pages/{page}', [PageController::class, 'update'])->name('pages.update');

    // Ações de gerenciamento de página (requer permissões administrativas)
    Route::middleware('permission:' . Permission::MANAGE_SYSTEM_SETTINGS->value)->group(function () {
        // Ativar/Desativar página
        Route::post('/pages/{page}/activate', [PageController::class, 'activate'])->name('pages.activate');
        Route::post('/pages/{page}/deactivate', [PageController::class, 'deactivate'])->name('pages.deactivate');

        // Tornar página pública/privada
        Route::post('/pages/{page}/make-public', [PageController::class, 'makePublic'])->name('pages.makePublic');
        Route::post('/pages/{page}/make-private', [PageController::class, 'makePrivate'])->name('pages.makePrivate');

        // Excluir página
        Route::delete('/pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
    });
});

// Rotas para gerenciamento de grupos de páginas
Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
    // Listar grupos de páginas
    Route::get('/page-groups', [PageGroupController::class, 'index'])->name('page-groups.index');

    // Visualizar grupo de páginas
    Route::get('/page-groups/{pageGroup}', [PageGroupController::class, 'show'])->name('page-groups.show');

    // Ações de gerenciamento (requer permissões administrativas)
    Route::middleware('permission:' . Permission::MANAGE_SYSTEM_SETTINGS->value)->group(function () {
        // Criar grupo de páginas
        Route::post('/page-groups', [PageGroupController::class, 'store'])->name('page-groups.store');

        // Atualizar grupo de páginas
        Route::put('/page-groups/{pageGroup}', [PageGroupController::class, 'update'])->name('page-groups.update');

        // Excluir grupo de páginas
        Route::delete('/page-groups/{pageGroup}', [PageGroupController::class, 'destroy'])->name('page-groups.destroy');
    });
});

// Rotas para gerenciamento de níveis de acesso
Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
    // Listar níveis de acesso
    Route::get('/access-levels', [AccessLevelController::class, 'index'])->name('access-levels.index');

    // Gerenciar permissões de um nível de acesso
    Route::get('/access-levels/{accessLevel}/permissions', [AccessLevelController::class, 'getPermissions'])
        ->name('access-levels.permissions');

    Route::post('/access-levels/{accessLevel}/permissions', [AccessLevelController::class, 'updatePermissions'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('access-levels.permissions.update');

    // Visualizar nível de acesso específico
    Route::get('/access-levels/{accessLevel}', [AccessLevelController::class, 'show'])->name('access-levels.show');

    // Criar nível de acesso
    Route::post('/access-levels', [AccessLevelController::class, 'store'])
        ->middleware('permission:' . Permission::CREATE_USERS->value)
        ->name('access-levels.store');

    // Atualizar nível de acesso
    Route::put('/access-levels/{accessLevel}', [AccessLevelController::class, 'update'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('access-levels.update');

    // Excluir nível de acesso
    Route::delete('/access-levels/{accessLevel}', [AccessLevelController::class, 'destroy'])
        ->middleware('permission:' . Permission::DELETE_USERS->value)
        ->name('access-levels.destroy');

    // Gerenciar páginas de um menu para um nível de acesso
    Route::get('/access-levels/{accessLevel}/menus/{menu}/pages', [AccessLevelPageController::class, 'manage'])
        ->name('access-levels.menus.pages.manage');

    Route::get('/access-levels/{accessLevel}/menus/{menu}/pages/json', [AccessLevelPageController::class, 'getPages'])
        ->name('access-levels.menus.pages.get');

    Route::post('/access-levels/{accessLevel}/menus/{menu}/pages', [AccessLevelPageController::class, 'updatePermissions'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('access-levels.menus.pages.update');
});

// Rotas para funcionários
Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
    // Exportar funcionários (deve vir antes do show para não conflitar com rotas)
    Route::get('/employees/export', [EmployeeController::class, 'export'])->name('employees.export');

    // Lista JSON de funcionários (para selects/modais)
    Route::get('/employees/list-json', [EmployeeController::class, 'listJson'])->name('employees.list-json');

    // Listar funcionários
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');

    // Criar funcionário
    Route::post('/employees', [EmployeeController::class, 'store'])
        ->middleware('permission:' . Permission::CREATE_USERS->value)
        ->name('employees.store');

    // Visualizar funcionário específico
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');

    // Editar funcionário (obter dados para edição)
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('employees.edit');

    // Atualizar funcionário (aceita PUT e POST para method spoofing)
    Route::match(['put', 'post'], '/employees/{employee}', [EmployeeController::class, 'update'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('employees.update');

    // Deletar funcionário
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
        ->middleware('permission:' . Permission::DELETE_USERS->value)
        ->name('employees.destroy');

    // Histórico do funcionário
    Route::get('/employees/{employee}/history', [EmployeeController::class, 'history'])
        ->name('employees.history');

    // Adicionar contrato
    Route::post('/employees/{employee}/contracts', [EmployeeController::class, 'storeContract'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('employees.contracts.store');

    // Atualizar contrato
    Route::put('/employees/{employee}/contracts/{contract}', [EmployeeController::class, 'updateContract'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('employees.contracts.update');

    // Excluir contrato
    Route::delete('/employees/{employee}/contracts/{contract}', [EmployeeController::class, 'destroyContract'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('employees.contracts.destroy');

    // Reativar contrato
    Route::post('/employees/{employee}/contracts/{contract}/reactivate', [EmployeeController::class, 'reactivateContract'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('employees.contracts.reactivate');

    // Eventos do funcionário
    Route::get('/employees/{employee}/events', [EmployeeController::class, 'getEvents'])
        ->name('employees.events.index');

    Route::post('/employees/{employee}/events', [EmployeeController::class, 'storeEvent'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('employees.events.store');

    Route::delete('/employees/{employee}/events/{event}', [EmployeeController::class, 'destroyEvent'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('employees.events.destroy');

    Route::get('/employees/{employee}/events/export', [EmployeeController::class, 'exportEvents'])
        ->name('employees.events.export');

    // Relatório completo do funcionário
    Route::get('/employees/{employee}/report', [EmployeeController::class, 'generateReport'])
        ->name('employees.report');

    Route::get('/employees/events/export', [EmployeeController::class, 'exportAllEvents'])
        ->name('employees.all-events.export');
});

// Rotas para controle de jornada
Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
    Route::get('/work-shifts', [WorkShiftController::class, 'index'])->name('work-shifts.index');

    Route::get('/work-shifts/export', [WorkShiftController::class, 'export'])
        ->name('work-shifts.export');

    Route::get('/work-shifts/print-summary', [WorkShiftController::class, 'printSummary'])
        ->name('work-shifts.print-summary');

    Route::post('/work-shifts', [WorkShiftController::class, 'store'])
        ->middleware('permission:' . Permission::CREATE_USERS->value)
        ->name('work-shifts.store');

    Route::get('/work-shifts/{workShift}', [WorkShiftController::class, 'show'])->name('work-shifts.show');

    Route::get('/work-shifts/{workShift}/edit', [WorkShiftController::class, 'edit'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('work-shifts.edit');

    Route::match(['put', 'post'], '/work-shifts/{workShift}', [WorkShiftController::class, 'update'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('work-shifts.update');

    Route::delete('/work-shifts/{workShift}', [WorkShiftController::class, 'destroy'])
        ->middleware('permission:' . Permission::DELETE_USERS->value)
        ->name('work-shifts.destroy');
});

// Rotas para escalas de trabalho
Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
    Route::get('/work-schedules', [WorkScheduleController::class, 'index'])->name('work-schedules.index');

    // Lista JSON de escalas ativas (para selects/modais) - deve vir antes de {workSchedule}
    Route::get('/work-schedules/list-json', [WorkScheduleController::class, 'listJson'])->name('work-schedules.list-json');

    Route::get('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'show'])->name('work-schedules.show');

    Route::get('/work-schedules/{workSchedule}/edit', [WorkScheduleController::class, 'edit'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('work-schedules.edit');

    Route::get('/work-schedules/{workSchedule}/employees', [WorkScheduleController::class, 'getEmployees'])
        ->name('work-schedules.employees');

    Route::post('/work-schedules', [WorkScheduleController::class, 'store'])
        ->middleware('permission:' . Permission::CREATE_USERS->value)
        ->name('work-schedules.store');

    Route::post('/work-schedules/{workSchedule}/duplicate', [WorkScheduleController::class, 'duplicate'])
        ->middleware('permission:' . Permission::CREATE_USERS->value)
        ->name('work-schedules.duplicate');

    Route::post('/work-schedules/{workSchedule}/employees', [WorkScheduleController::class, 'assignEmployee'])
        ->middleware('permission:' . Permission::CREATE_USERS->value)
        ->name('work-schedules.assign-employee');

    Route::match(['put', 'post'], '/work-schedules/{workSchedule}/update', [WorkScheduleController::class, 'update'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('work-schedules.update');

    Route::delete('/work-schedules/{workSchedule}', [WorkScheduleController::class, 'destroy'])
        ->middleware('permission:' . Permission::DELETE_USERS->value)
        ->name('work-schedules.destroy');

    Route::delete('/work-schedules/{workSchedule}/employees/{assignment}', [WorkScheduleController::class, 'unassignEmployee'])
        ->middleware('permission:' . Permission::DELETE_USERS->value)
        ->name('work-schedules.unassign-employee');

    // Overrides de dia por funcionário
    Route::post('/employee-schedules/{assignment}/overrides', [WorkScheduleController::class, 'storeOverride'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('employee-schedules.overrides.store');

    Route::delete('/employee-schedules/{assignment}/overrides/{override}', [WorkScheduleController::class, 'destroyOverride'])
        ->middleware('permission:' . Permission::DELETE_USERS->value)
        ->name('employee-schedules.overrides.destroy');

    // Escala do funcionário (via EmployeeController)
    Route::get('/employees/{employee}/work-schedule', [EmployeeController::class, 'getWorkSchedule'])
        ->name('employees.work-schedule');
});

// Rotas para lojas
Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
    // API para select de lojas
    Route::get('/api/stores/select', [StoreController::class, 'getForSelect'])->name('stores.select');

    // Listar lojas
    Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');

    // Reordenar lojas (antes das rotas com parâmetro para evitar conflito)
    Route::post('/stores/reorder', [StoreController::class, 'reorder'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('stores.reorder');

    // Criar loja
    Route::post('/stores', [StoreController::class, 'store'])
        ->middleware('permission:' . Permission::CREATE_USERS->value)
        ->name('stores.store');

    // Visualizar loja específica
    Route::get('/stores/{store}', [StoreController::class, 'show'])->name('stores.show');

    // Editar loja
    Route::get('/stores/{store}/edit', [StoreController::class, 'edit'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('stores.edit');

    Route::match(['put', 'post'], '/stores/{store}', [StoreController::class, 'update'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('stores.update');

    // Deletar loja
    Route::delete('/stores/{store}', [StoreController::class, 'destroy'])
        ->middleware('permission:' . Permission::DELETE_USERS->value)
        ->name('stores.destroy');

    // Ativar/Desativar loja
    Route::post('/stores/{store}/activate', [StoreController::class, 'activate'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('stores.activate');

    Route::post('/stores/{store}/deactivate', [StoreController::class, 'deactivate'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('stores.deactivate');
});

// Rotas para gerenciamento de temas de cores
Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
    // Listar temas de cores
    Route::get('/color-themes', [ColorThemeController::class, 'index'])->name('color-themes.index');

    // Criar tema de cor
    Route::post('/color-themes', [ColorThemeController::class, 'store'])
        ->middleware('permission:' . Permission::CREATE_USERS->value)
        ->name('color-themes.store');

    // Atualizar tema de cor
    Route::put('/color-themes/{colorTheme}', [ColorThemeController::class, 'update'])
        ->middleware('permission:' . Permission::EDIT_USERS->value)
        ->name('color-themes.update');

    // Excluir tema de cor
    Route::delete('/color-themes/{colorTheme}', [ColorThemeController::class, 'destroy'])
        ->middleware('permission:' . Permission::DELETE_USERS->value)
        ->name('color-themes.destroy');
});

// Rotas de Configuracao
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
});

// Rotas para vendas (Comercial)
Route::middleware(['auth', 'permission:' . Permission::VIEW_SALES->value])->group(function () {
    Route::get('/sales/statistics', [SaleController::class, 'statistics'])->name('sales.statistics');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');

    Route::post('/sales', [SaleController::class, 'store'])
        ->middleware('permission:' . Permission::CREATE_SALES->value)
        ->name('sales.store');

    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('/sales/{sale}/edit', [SaleController::class, 'edit'])
        ->middleware('permission:' . Permission::EDIT_SALES->value)
        ->name('sales.edit');
    Route::put('/sales/{sale}', [SaleController::class, 'update'])
        ->middleware('permission:' . Permission::EDIT_SALES->value)
        ->name('sales.update');
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])
        ->middleware('permission:' . Permission::DELETE_SALES->value)
        ->name('sales.destroy');

    // Sincronização CIGAM
    Route::post('/sales/sync/auto', [SaleController::class, 'syncAuto'])
        ->middleware('permission:' . Permission::CREATE_SALES->value)
        ->name('sales.sync.auto');
    Route::post('/sales/sync/month', [SaleController::class, 'syncByMonth'])
        ->middleware('permission:' . Permission::CREATE_SALES->value)
        ->name('sales.sync.month');
    Route::post('/sales/sync/range', [SaleController::class, 'syncByDateRange'])
        ->middleware('permission:' . Permission::CREATE_SALES->value)
        ->name('sales.sync.range');

    // Exclusão em lote
    Route::post('/sales/bulk-delete/preview', [SaleController::class, 'bulkDeletePreview'])
        ->middleware('permission:' . Permission::DELETE_SALES->value)
        ->name('sales.bulk-delete.preview');
    Route::post('/sales/bulk-delete', [SaleController::class, 'bulkDelete'])
        ->middleware('permission:' . Permission::DELETE_SALES->value)
        ->name('sales.bulk-delete');
});

// Rotas para páginas básicas ainda não implementadas (placeholder)
Route::middleware(['auth'])->group(function () {
    // Menu Principal
    Route::get('/produto', function () {
        return Inertia::render('ComingSoon', ['title' => 'Produto']);
    })->name('produto');

    Route::get('/planejamento', function () {
        return Inertia::render('ComingSoon', ['title' => 'Planejamento']);
    })->name('planejamento');

    Route::get('/financeiro', function () {
        return Inertia::render('ComingSoon', ['title' => 'Financeiro']);
    })->name('financeiro');

    Route::get('/ativo-fixo', function () {
        return Inertia::render('ComingSoon', ['title' => 'Ativo Fixo']);
    })->name('ativo-fixo');

    Route::get('/comercial', function () {
        return redirect('/sales');
    })->name('comercial');

    Route::get('/delivery', function () {
        return Inertia::render('ComingSoon', ['title' => 'Delivery']);
    })->name('delivery');

    Route::get('/rotas', function () {
        return Inertia::render('ComingSoon', ['title' => 'Rotas']);
    })->name('rotas');

    Route::get('/ecommerce', function () {
        return Inertia::render('ComingSoon', ['title' => 'E-commerce']);
    })->name('ecommerce');

    // Sistema
    Route::get('/qualidade', function () {
        return Inertia::render('ComingSoon', ['title' => 'Qualidade']);
    })->name('qualidade');

    // Recursos Humanos
    Route::get('/pessoas-cultura', function () {
        return Inertia::render('ComingSoon', ['title' => 'Pessoas & Cultura']);
    })->name('pessoas-cultura');

    Route::get('/departamento-pessoal', function () {
        return Inertia::render('ComingSoon', ['title' => 'Departamento Pessoal']);
    })->name('departamento-pessoal');

    // Utilidades
    Route::get('/escola-digital', function () {
        return Inertia::render('ComingSoon', ['title' => 'Escola Digital']);
    })->name('escola-digital');

    Route::get('/movidesk', function () {
        return Inertia::render('ComingSoon', ['title' => 'Movidesk']);
    })->name('movidesk');

    Route::get('/biblioteca-processos', function () {
        return Inertia::render('ComingSoon', ['title' => 'Biblioteca de Processos']);
    })->name('biblioteca-processos');
});

require __DIR__.'/auth.php';
