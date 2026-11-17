<?php

declare(strict_types=1);

use HomeFlix\Identity\UI\Http\Controllers\AdminDashboardController;
use Illuminate\Support\Facades\Route;

// Toutes les routes d'administration passent par `auth` puis `admin`.
Route::middleware(['web', 'auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
    });
