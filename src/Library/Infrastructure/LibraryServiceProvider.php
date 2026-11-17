<?php

declare(strict_types=1);

namespace HomeFlix\Library\Infrastructure;

use HomeFlix\Library\Application\Port\FavoritesReader;
use HomeFlix\Library\Application\Port\TitleDirectory;
use HomeFlix\Library\Domain\Repository\FavoriteRepository;
use HomeFlix\Library\Infrastructure\Persistence\DatabaseFavoriteRepository;
use HomeFlix\Library\Infrastructure\Persistence\DatabaseFavoritesReader;
use HomeFlix\Library\Infrastructure\Persistence\DatabaseTitleDirectory;
use Illuminate\Support\ServiceProvider;

final class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FavoriteRepository::class, DatabaseFavoriteRepository::class);
        $this->app->bind(TitleDirectory::class, DatabaseTitleDirectory::class);
        $this->app->bind(FavoritesReader::class, DatabaseFavoritesReader::class);
    }
}
