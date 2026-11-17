<?php

declare(strict_types=1);

use HomeFlix\Library\UI\Http\Controllers\FavoritesController;
use HomeFlix\Library\UI\Http\Controllers\WatchProgressController;
use Illuminate\Support\Facades\Route;

// Réservé aux membres connectés.
Route::middleware(['web', 'auth'])->get('favoris', [FavoritesController::class, 'index'])->name('favorites.index');

// Le lecteur envoie la position toutes les 10 secondes : la limite laisse de la marge, y compris avec deux onglets.
Route::middleware(['web', 'auth', 'throttle:60,1'])
    ->prefix('progression')
    ->name('progress.')
    ->controller(WatchProgressController::class)
    ->group(function () {
        Route::get('{media}', 'show')->whereUuid('media')->name('show');
        Route::put('{media}', 'save')->whereUuid('media')->name('save');
    });
