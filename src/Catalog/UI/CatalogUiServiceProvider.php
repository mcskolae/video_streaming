<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI;

use Illuminate\Support\ServiceProvider;

/** Branche l'UI du contexte Catalog : routes et vues. */
final class CatalogUiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/Views', 'catalog');
    }
}
