<?php

declare(strict_types=1);

use Plugs\Facades\Route;
use Modules\Admin\Controllers\AdminUserController;

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

Route::resource('users', AdminUserController::class);
