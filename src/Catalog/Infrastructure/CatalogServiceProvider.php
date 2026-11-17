<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure;

use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Catalog\Infrastructure\Persistence\EloquentTitleRepository;
use Illuminate\Support\ServiceProvider;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TitleRepository::class, EloquentTitleRepository::class);
    }
}
