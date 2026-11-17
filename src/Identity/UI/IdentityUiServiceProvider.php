<?php

declare(strict_types=1);

namespace HomeFlix\Identity\UI;

use App\Models\User;
use HomeFlix\Identity\UI\Console\MakeAdministratorCommand;
use HomeFlix\Identity\UI\Http\Middleware\EnsureUserIsAdministrator;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/** Branche l'UI du contexte Identity : autorisation, middleware, routes, vues et commande. */
final class IdentityUiServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        // Seuls les administrateurs peuvent administrer. Un visiteur n'a pas de rôle : refus.
        Gate::define('administer', fn (User $user): bool => $user->role->isAdministrator());

        $router->aliasMiddleware('admin', EnsureUserIsAdministrator::class);

        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/Views', 'identity');

        if ($this->app->runningInConsole()) {
            $this->commands([MakeAdministratorCommand::class]);
        }
    }
}
