<?php

declare(strict_types=1);

use HomeFlix\Media\UI\Http\Controllers\MediaUploadController;
use Illuminate\Support\Facades\Route;

// Toutes les routes passent par `auth` puis `admin`.
Route::middleware(['web', 'auth', 'admin'])
    ->prefix('admin/media')
    ->name('admin.media.')
    ->controller(MediaUploadController::class)
    ->group(function () {
        Route::get('{kind}/{target}', 'show')->where('kind', 'title|episode')->whereUuid('target')->name('show');

        // Un envoi fait des centaines de requêtes (une par morceau de 5 Mo) : la limite est large.
        Route::middleware('throttle:3000,1')->prefix('uploads')->name('uploads.')->group(function () {
            Route::post('/', 'start')->name('start');
            Route::get('{upload}', 'status')->whereUuid('upload')->name('status');
            Route::put('{upload}/chunks/{index}', 'chunk')->whereUuid('upload')->where('index', '[0-9]{1,6}')->name('chunk');
            Route::post('{upload}/complete', 'complete')->whereUuid('upload')->name('complete');
        });
    });
