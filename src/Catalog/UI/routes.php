<?php

declare(strict_types=1);

use HomeFlix\Catalog\UI\Http\Controllers\Admin\TitleAdminController;
use Illuminate\Support\Facades\Route;

// Toutes les routes d'administration passent par `auth` puis `admin`.
// Les appels à TMDB sont limités pour protéger le quota de la clé.
Route::middleware(['web', 'auth', 'admin'])
    ->prefix('admin/titles')
    ->name('admin.titles.')
    ->controller(TitleAdminController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');

        Route::middleware('throttle:30,1')->group(function () {
            Route::get('import', 'search')->name('search');
            Route::get('import/{tmdbId}', 'preview')->where('tmdbId', '[1-9][0-9]{0,9}')->name('preview');
            Route::post('import', 'import')->name('import');
        });

        Route::post('{title}/publish', 'publish')->whereUuid('title')->name('publish');
        Route::post('{title}/unpublish', 'unpublish')->whereUuid('title')->name('unpublish');
    });
