<?php

declare(strict_types=1);

use Plugs\Facades\Route;
use Modules\Admin\Controllers\AdminDashboardController;
use Modules\Admin\Controllers\AdminUserController;
use Modules\Admin\Controllers\AdminSettingsController;
use Modules\Admin\Controllers\AdminProfileController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your module. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
Route::resource('users', AdminUserController::class);

Route::get('/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
Route::post('/settings', [AdminSettingsController::class, 'store'])->name('admin.settings.store');

Route::get('/profile', [AdminProfileController::class, 'index'])->name('admin.profile.index');
Route::post('/profile', [AdminProfileController::class, 'update'])->name('admin.profile.update');
