<?php

declare(strict_types=1);

namespace HomeFlix\Media\UI;

use HomeFlix\Media\UI\Console\PurgeStaleUploadsCommand;
use HomeFlix\Media\UI\Livewire\MediaList;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class MediaUiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/Views', 'media');

        Livewire::component('media.admin-list', MediaList::class);

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeStaleUploadsCommand::class]);
        }
    }
}
