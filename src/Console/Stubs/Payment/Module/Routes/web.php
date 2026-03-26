<?php

declare(strict_types=1);

use Plugs\Facades\Route;
use Modules\Payment\Controllers\PaymentSettingsController;

/*
|--------------------------------------------------------------------------
| Payment Module Routes
|--------------------------------------------------------------------------
*/

// Payment Settings
Route::get('/', [PaymentSettingsController::class, 'index'])->name('settings.index');
Route::post('/', [PaymentSettingsController::class, 'store'])->name('settings.store');

