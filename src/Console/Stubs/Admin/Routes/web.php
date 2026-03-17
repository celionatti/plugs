<?php

declare(strict_types=1);

use Plugs\Facades\Route;
use Modules\Admin\Controllers\AdminDashboardController;
use Modules\Admin\Controllers\AdminUserController;
use Modules\Admin\Controllers\AdminSettingsController;
use Modules\Admin\Controllers\AdminProfileController;
use Modules\Admin\Controllers\AdminModuleController;
use Modules\Admin\Controllers\AdminThemeController;
use Modules\Admin\Controllers\AdminArticleController;
use Modules\Admin\Controllers\LogController;
use Modules\Admin\Controllers\MigrationController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

// Users CRUD
Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
Route::get('/users/{id}', [AdminUserController::class, 'show'])->name('users.show');
Route::get('/users/{id}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
Route::post('/users/{id}', [AdminUserController::class, 'update'])->name('users.update');
Route::put('/users/{id}', [AdminUserController::class, 'update']);
Route::post('/users/{id}/delete', [AdminUserController::class, 'destroy'])->name('users.destroy');
Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

// Articles CRUD
Route::get('/articles', [AdminArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/create', [AdminArticleController::class, 'create'])->name('articles.create');
Route::post('/articles', [AdminArticleController::class, 'store'])->name('articles.store');
Route::get('/articles/{id}/edit', [AdminArticleController::class, 'edit'])->name('articles.edit');
Route::post('/articles/{id}', [AdminArticleController::class, 'update'])->name('articles.update');
Route::put('/articles/{id}', [AdminArticleController::class, 'update']);
Route::post('/articles/{id}/delete', [AdminArticleController::class, 'destroy'])->name('articles.destroy');
Route::delete('/articles/{id}', [AdminArticleController::class, 'destroy']);

// Settings
Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
Route::post('/settings', [AdminSettingsController::class, 'store'])->name('settings.store');

// Profile
Route::get('/profile', [AdminProfileController::class, 'index'])->name('profile.index');
Route::post('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
Route::post('/profile/update', [AdminProfileController::class, 'update']);

// Themes
Route::get('/themes', [AdminThemeController::class, 'index'])->name('themes.index');
Route::get('/themes/{name}/screenshot', [AdminThemeController::class, 'screenshot'])->name('themes.screenshot');
Route::post('/themes/{name}/activate', [AdminThemeController::class, 'activate'])->name('themes.activate');

// Modules
Route::get('/modules', [AdminModuleController::class, 'index'])->name('modules.index');
Route::get('/modules/create', [AdminModuleController::class, 'create'])->name('modules.create');
Route::post('/modules', [AdminModuleController::class, 'store'])->name('modules.store');
Route::post('/modules/{name}/toggle', [AdminModuleController::class, 'toggle'])->name('modules.toggle');
Route::get('/modules/{name}/configure', [AdminModuleController::class, 'show'])->name('modules.configure');
Route::post('/modules/{name}/settings', [AdminModuleController::class, 'updateSettings'])->name('modules.settings');
Route::post('/modules/{name}/delete', [AdminModuleController::class, 'destroy'])->name('modules.destroy');
Route::delete('/modules/{name}', [AdminModuleController::class, 'destroy']);

// Log Viewer Routes
Route::get('/logs', [LogController::class, 'index'])->name('logs');
Route::get('/logs/api', [LogController::class, 'fetch'])->name('logs.api');
Route::post('/logs/clear', [LogController::class, 'clear'])->name('logs.clear');

// Migration Management
Route::get('/migrations', [MigrationController::class, 'index'])->name('migrations.index');
Route::post('/migrations/run', [MigrationController::class, 'migrate'])->name('migrations.run');
Route::post('/migrations/rollback', [MigrationController::class, 'rollback'])->name('migrations.rollback');
Route::post('/migrations/fresh', [MigrationController::class, 'fresh'])->name('migrations.fresh');
