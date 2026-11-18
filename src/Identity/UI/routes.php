<?php

declare(strict_types=1);

use HomeFlix\Identity\UI\Http\Controllers\UserAdminController;
use Illuminate\Support\Facades\Route;

// Toutes les routes d'administration passent par `auth` puis `admin`.
Route::middleware(['web', 'auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // `/admin` n'a pas de page propre : il mène au catalogue.
        Route::redirect('/', '/admin/titles');

        Route::prefix('users')->name('users.')->controller(UserAdminController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('{user}/role', 'changeRole')->whereUuid('user')->name('role');
            Route::post('{user}/suspend', 'suspend')->whereUuid('user')->name('suspend');
            Route::post('{user}/reactivate', 'reactivate')->whereUuid('user')->name('reactivate');
        });
    });
