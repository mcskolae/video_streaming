<?php

declare(strict_types=1);

namespace HomeFlix\Library\UI;

use HomeFlix\Library\UI\Livewire\FavoriteButton;
use HomeFlix\Library\UI\View\ContinueWatching;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class LibraryUiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/Views', 'library');

        Blade::component('continue-watching', ContinueWatching::class, 'library');
        Livewire::component('library.favorite-button', FavoriteButton::class);
    }
}
