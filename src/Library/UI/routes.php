<?php

declare(strict_types=1);

use HomeFlix\Library\UI\Http\Controllers\FavoritesController;
use Illuminate\Support\Facades\Route;

// Réservé aux membres connectés.
Route::middleware(['web', 'auth'])->get('favoris', [FavoritesController::class, 'index'])->name('favorites.index');
