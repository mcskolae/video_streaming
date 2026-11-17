<?php

declare(strict_types=1);

namespace HomeFlix\Media\UI;

use HomeFlix\Media\UI\Console\PurgeStaleUploadsCommand;
use Illuminate\Support\ServiceProvider;

final class MediaUiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/Views', 'media');

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeStaleUploadsCommand::class]);
        }
    }
}
