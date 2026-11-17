<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\UI;

use HomeFlix\Catalog\Application\Query\PublicCatalog;
use HomeFlix\Catalog\UI\Http\Middleware\LimitLivewireUpdates;
use HomeFlix\Catalog\UI\Livewire\SearchTitles;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Branche l'UI du contexte Catalog : routes, vues, composant Livewire et menu des genres. */
final class CatalogUiServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/Views', 'catalog');

        Livewire::component('catalog.search-titles', SearchTitles::class);

        // La recherche en direct envoie une requête par frappe : on les limite par adresse IP, sur toutes les routes de mise à jour.
        $router->pushMiddlewareToGroup('web', LimitLivewireUpdates::class);

        // Le menu des genres est affiché sur toutes les pages publiques.
        ViewFacade::composer('catalog::components.public-layout', function (View $view): void {
            $view->with('navGenres', app(PublicCatalog::class)->genres());
        });
    }
}
