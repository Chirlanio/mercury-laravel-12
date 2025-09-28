<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
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

require __DIR__.'/auth.php';
