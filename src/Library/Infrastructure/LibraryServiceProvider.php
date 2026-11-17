<?php

declare(strict_types=1);

namespace HomeFlix\Library\Infrastructure;

use HomeFlix\Library\Application\Port\ContinueWatchingReader;
use HomeFlix\Library\Application\Port\FavoritesReader;
use HomeFlix\Library\Application\Port\MediaDirectory;
use HomeFlix\Library\Application\Port\TitleDirectory;
use HomeFlix\Library\Domain\Repository\FavoriteRepository;
use HomeFlix\Library\Domain\Repository\WatchProgressRepository;
use HomeFlix\Library\Infrastructure\Persistence\DatabaseContinueWatchingReader;
use HomeFlix\Library\Infrastructure\Persistence\DatabaseFavoriteRepository;
use HomeFlix\Library\Infrastructure\Persistence\DatabaseFavoritesReader;
use HomeFlix\Library\Infrastructure\Persistence\DatabaseMediaDirectory;
use HomeFlix\Library\Infrastructure\Persistence\DatabaseTitleDirectory;
use HomeFlix\Library\Infrastructure\Persistence\DatabaseWatchProgressRepository;
use Illuminate\Support\ServiceProvider;

final class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FavoriteRepository::class, DatabaseFavoriteRepository::class);
        $this->app->bind(TitleDirectory::class, DatabaseTitleDirectory::class);
        $this->app->bind(FavoritesReader::class, DatabaseFavoritesReader::class);
        $this->app->bind(WatchProgressRepository::class, DatabaseWatchProgressRepository::class);
        $this->app->bind(MediaDirectory::class, DatabaseMediaDirectory::class);
        $this->app->bind(ContinueWatchingReader::class, DatabaseContinueWatchingReader::class);
    }
}
