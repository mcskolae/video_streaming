<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure;

use HomeFlix\Catalog\Application\Port\MetadataProvider;
use HomeFlix\Catalog\Application\Port\TitleListReader;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Catalog\Infrastructure\Persistence\DatabaseTitleListReader;
use HomeFlix\Catalog\Infrastructure\Persistence\EloquentTitleRepository;
use HomeFlix\Catalog\Infrastructure\Tmdb\TmdbClient;
use HomeFlix\Catalog\Infrastructure\Tmdb\TmdbMetadataProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TitleRepository::class, EloquentTitleRepository::class);
        $this->app->bind(TitleListReader::class, DatabaseTitleListReader::class);

        $this->app->bind(TmdbClient::class, function (Application $app): TmdbClient {
            // La clé vient de config/services.php, qui la lit dans un secret Docker via secret().
            $key = config('services.tmdb.key');
            $baseUrl = config('services.tmdb.base_url');
            $language = config('services.tmdb.language');

            return new TmdbClient(
                $app->make(Http::class),
                $app->make(Cache::class),
                is_string($key) ? $key : null,
                is_string($baseUrl) ? $baseUrl : 'https://api.themoviedb.org/3',
                is_string($language) ? $language : 'fr-FR',
            );
        });

        $this->app->bind(MetadataProvider::class, TmdbMetadataProvider::class);
    }
}
