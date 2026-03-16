<?php

declare(strict_types=1);

use Plugs\Facades\Route;
use Modules\Admin\Controllers\AdminDashboardController;
use Modules\Admin\Controllers\AdminUserController;
use Modules\Admin\Controllers\AdminSettingsController;
use Modules\Admin\Controllers\AdminProfileController;
use Modules\Admin\Controllers\AdminModuleController;
use Modules\Admin\Controllers\AdminArticleController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

// Users CRUD
Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
Route::get('/users/create', [AdminUserController::class, 'create'])->name('admin.users.create');
Route::post('/users', [AdminUserController::class, 'store'])->name('admin.users.store');
Route::get('/users/{id}', [AdminUserController::class, 'show'])->name('admin.users.show');
Route::get('/users/{id}/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit');
Route::post('/users/{id}', [AdminUserController::class, 'update'])->name('admin.users.update');
Route::put('/users/{id}', [AdminUserController::class, 'update']);
Route::post('/users/{id}/delete', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

// Articles CRUD
Route::get('/articles', [AdminArticleController::class, 'index'])->name('admin.articles.index');
Route::get('/articles/create', [AdminArticleController::class, 'create'])->name('admin.articles.create');
Route::post('/articles', [AdminArticleController::class, 'store'])->name('admin.articles.store');
Route::get('/articles/{id}/edit', [AdminArticleController::class, 'edit'])->name('admin.articles.edit');
Route::post('/articles/{id}', [AdminArticleController::class, 'update'])->name('admin.articles.update');
Route::put('/articles/{id}', [AdminArticleController::class, 'update']);
Route::post('/articles/{id}/delete', [AdminArticleController::class, 'destroy'])->name('admin.articles.destroy');
Route::delete('/articles/{id}', [AdminArticleController::class, 'destroy']);

// Settings
Route::get('/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
Route::post('/settings', [AdminSettingsController::class, 'store'])->name('admin.settings.store');

// Profile
Route::get('/profile', [AdminProfileController::class, 'index'])->name('admin.profile.index');
Route::post('/profile', [AdminProfileController::class, 'update'])->name('admin.profile.update');
Route::post('/profile/update', [AdminProfileController::class, 'update']);

// Modules
Route::get('/modules', [AdminModuleController::class, 'index'])->name('admin.modules.index');
