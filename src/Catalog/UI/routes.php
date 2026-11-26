<?php

declare(strict_types=1);

use HomeFlix\Catalog\UI\Http\Controllers\Admin\TitleAdminController;
use HomeFlix\Catalog\UI\Http\Controllers\PublicCatalogController;
use HomeFlix\Catalog\UI\Http\Controllers\SeoController;
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
            Route::get('import/series/{tmdbId}', 'previewSeries')->where('tmdbId', '[1-9][0-9]{0,9}')->name('preview-series');
            Route::post('import/series', 'importSeries')->name('import-series');
        });

        Route::get('{title}', 'show')->whereUuid('title')->name('show');
        Route::post('{title}/publish', 'publish')->whereUuid('title')->name('publish');
        Route::post('{title}/unpublish', 'unpublish')->whereUuid('title')->name('unpublish');
    });

// Pages publiques : sans compte. Les appels sont limités par adresse IP pour freiner l'aspiration du catalogue.
Route::middleware(['web', 'throttle:240,1'])
    ->controller(PublicCatalogController::class)
    ->group(function () {
        Route::get('/', 'home')->name('home');
        Route::get('recherche', 'search')->name('search');
        Route::get('titres/{slug}', 'show')->where('slug', '[a-z0-9-]{1,120}')->name('titles.show');
    });

// Référencement : lus par les moteurs de recherche. robots.txt est dynamique pour donner l'adresse absolue du sitemap.
Route::middleware(['web', 'throttle:240,1'])
    ->controller(SeoController::class)
    ->group(function () {
        Route::get('sitemap.xml', 'sitemap')->name('sitemap');
        Route::get('robots.txt', 'robots')->name('robots');
    });
