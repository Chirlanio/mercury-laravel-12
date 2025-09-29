<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AccessLevelController;
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
    Route::patch('/users/{user}', [UserManagementController::class, 'update'])
        ->middleware('permission:' . Permission::EDIT_USERS->value . ',' . Permission::EDIT_ANY_PROFILE->value)
        ->name('users.update');
    Route::post('/users/{user}/update-with-files', [UserManagementController::class, 'updateWithFiles'])
        ->middleware('permission:' . Permission::EDIT_USERS->value . ',' . Permission::EDIT_ANY_PROFILE->value)
        ->name('users.updateWithFiles');

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

Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
    // Listar menus
    Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');

    // Visualizar menu específico
    Route::get('/menus/{menu}', [MenuController::class, 'show'])->name('menus.show');

    // Ações de gerenciamento de menu (requer permissões administrativas)
    Route::middleware('permission:' . Permission::MANAGE_SYSTEM_SETTINGS->value)->group(function () {
        // Ativar/Desativar menu
        Route::post('/menus/{menu}/activate', [MenuController::class, 'activate'])->name('menus.activate');
        Route::post('/menus/{menu}/deactivate', [MenuController::class, 'deactivate'])->name('menus.deactivate');

        // Mover menu para cima/baixo
        Route::post('/menus/{menu}/move-up', [MenuController::class, 'moveUp'])->name('menus.moveUp');
        Route::post('/menus/{menu}/move-down', [MenuController::class, 'moveDown'])->name('menus.moveDown');

        // Reordenar menus
        Route::post('/menus/reorder', [MenuController::class, 'reorder'])->name('menus.reorder');
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
    });
});

// Rotas para gerenciamento de níveis de acesso
Route::middleware(['auth', 'permission:' . Permission::VIEW_USERS->value])->group(function () {
    // Listar níveis de acesso
    Route::get('/access-levels', [AccessLevelController::class, 'index'])->name('access-levels.index');

    // Visualizar nível de acesso específico
    Route::get('/access-levels/{accessLevel}', [AccessLevelController::class, 'show'])->name('access-levels.show');
});

require __DIR__.'/auth.php';
