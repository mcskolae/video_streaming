<?php

declare(strict_types=1);

use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Application\Port\MetadataProvider;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use HomeFlix\Catalog\Infrastructure\Tmdb\TmdbMetadataProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\Tmdb\TmdbFixtures as Tmdb;

beforeEach(function () {
    Sleep::fake();
    Http::preventStrayRequests();
    config(['services.tmdb.key' => '0123456789abcdef0123456789abcdef']);
    $this->provider = app(MetadataProvider::class);
});

/** @return list<string> chemins appelés, sans la requête */
function calledPaths(): array
{
    return Http::recorded()->map(fn (array $pair) => parse_url($pair[0]->url(), PHP_URL_PATH))->values()->all();
}

it('is the TMDB adapter behind the port', function () {
    expect(app(MetadataProvider::class))->toBeInstanceOf(TmdbMetadataProvider::class);
});

describe('movie', function () {
    it('fetches and maps a movie', function () {
        Http::fake(['api.themoviedb.org/3/movie/10378*' => Http::response(Tmdb::movie())]);

        $movie = $this->provider->movie(new TmdbId(10378));

        expect($movie->tmdbId->value)->toBe(10378)
            ->and($movie->details->name)->toBe('Big Buck Bunny')
            ->and($movie->details->releaseYear)->toBe(2008)
            ->and(calledPaths())->toBe(['/3/movie/10378']);
    });

    it('reports an unknown movie', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['status_code' => 34], 404)]);

        expect(fn () => $this->provider->movie(new TmdbId(1)))
            ->toThrow(MetadataNotFound::class, 'No movie found for TMDB id 1');
    });

    it('reports a response it cannot use', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 1])]);

        expect(fn () => $this->provider->movie(new TmdbId(1)))->toThrow(MetadataProviderUnavailable::class);
    });

    it('uses the cache for a second call', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(Tmdb::movie())]);

        $this->provider->movie(new TmdbId(10378));
        $this->provider->movie(new TmdbId(10378));

        Http::assertSentCount(1);
    });
});

describe('series', function () {
    it('fetches the series then every season, specials included', function () {
        Http::fake([
            'api.themoviedb.org/3/tv/1399/season/*' => fn (Request $request) => Http::response(
                Tmdb::season((int) basename(parse_url($request->url(), PHP_URL_PATH))),
            ),
            'api.themoviedb.org/3/tv/1399*' => Http::response(Tmdb::series()),
        ]);

        $series = $this->provider->series(new TmdbId(1399));

        expect(calledPaths())->toBe(['/3/tv/1399', '/3/tv/1399/season/0', '/3/tv/1399/season/1', '/3/tv/1399/season/2'])
            ->and(array_map(fn ($s) => $s->number, $series->seasons))->toBe([0, 1, 2])
            ->and($series->seasons[2]->episodes)->toHaveCount(2)
            ->and($series->details->name)->toBe('Sintel Chronicles');
    });

    it('keeps a season TMDB lists but cannot describe', function () {
        Http::fake([
            'api.themoviedb.org/3/tv/1399/season/1*' => Http::response(Tmdb::season(1)),
            'api.themoviedb.org/3/tv/1399/season/*' => Http::response([], 404),
            'api.themoviedb.org/3/tv/1399*' => Http::response(Tmdb::series()),
        ]);

        $series = $this->provider->series(new TmdbId(1399));

        expect($series->seasons[0]->episodes)->toBe([])
            ->and($series->seasons[1]->episodes)->toHaveCount(2)
            ->and($series->seasons[2]->episodes)->toBe([]);
    });

    it('reports an unknown series', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response([], 404)]);

        expect(fn () => $this->provider->series(new TmdbId(5)))
            ->toThrow(MetadataNotFound::class, 'No series found for TMDB id 5');
    });

    it('fails when a season request fails for another reason', function () {
        Http::fake([
            'api.themoviedb.org/3/tv/1399/season/*' => Http::response([], 429),
            'api.themoviedb.org/3/tv/1399*' => Http::response(Tmdb::series()),
        ]);

        expect(fn () => $this->provider->series(new TmdbId(1399)))->toThrow(MetadataProviderUnavailable::class, 'rate limit');
    });

    it('accepts a series with no season', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 3, 'name' => 'Vide'])]);

        expect($this->provider->series(new TmdbId(3))->seasons)->toBe([])
            ->and(calledPaths())->toBe(['/3/tv/3']);
    });
});

describe('search', function () {
    it('searches movies', function () {
        Http::fake(['api.themoviedb.org/3/search/movie*' => Http::response(Tmdb::movieSearch())]);

        $results = $this->provider->search('Big Buck', TitleType::Movie);

        expect($results)->toHaveCount(2)
            ->and($results[0]->name)->toBe('Big Buck Bunny');
        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return parse_url($request->url(), PHP_URL_PATH) === '/3/search/movie'
                && $query['query'] === 'Big Buck'
                && $query['include_adult'] === 'false'
                && $query['language'] === 'fr-FR';
        });
    });

    it('searches series on the series endpoint', function () {
        Http::fake(['api.themoviedb.org/3/search/tv*' => Http::response(['results' => [['id' => 1, 'name' => 'Une série']]])]);

        $results = $this->provider->search('série', TitleType::Series);

        expect($results[0]->type)->toBe(TitleType::Series)
            ->and(calledPaths())->toBe(['/3/search/tv']);
    });

    it('does not call TMDB for an empty search', function (string $query) {
        expect($this->provider->search($query, TitleType::Movie))->toBe([]);
        Http::assertNothingSent();
    })->with([[''], ['   '], ["\n\t"]]);

    it('trims the query and cuts it to 200 characters', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['results' => []])]);

        $this->provider->search('  '.str_repeat('a', 300).'  ', TitleType::Movie);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['query'] === str_repeat('a', 200);
        });
    });

    it('sends special characters safely encoded', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['results' => []])]);

        $this->provider->search('a&api_key=evil#x /../', TitleType::Movie);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query['query'] === 'a&api_key=evil#x /../'
                && $query['api_key'] === '0123456789abcdef0123456789abcdef'
                && parse_url($request->url(), PHP_URL_HOST) === 'api.themoviedb.org';
        });
    });

    it('turns a TMDB failure into an explicit error', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response([], 500)]);

        expect(fn () => $this->provider->search('a', TitleType::Movie))->toThrow(MetadataProviderUnavailable::class);
    });
});

describe('configuration', function () {
    it('fails clearly without a key and sends nothing', function () {
        config(['services.tmdb.key' => null]);

        expect(fn () => app(MetadataProvider::class)->movie(new TmdbId(1)))
            ->toThrow(MetadataProviderUnavailable::class, 'not configured');
        Http::assertNothingSent();
    });

    it('reads the key from the Docker secret file, not from the repository', function () {
        $directory = sys_get_temp_dir().'/secrets-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/tmdb_api_key', "from-the-secret-file\n");
        putenv('SECRETS_PATH='.$directory);

        try {
            $services = require config_path('services.php');
        } finally {
            putenv('SECRETS_PATH');
            unlink($directory.'/tmdb_api_key');
            rmdir($directory);
        }

        expect($services['tmdb']['key'])->toBe('from-the-secret-file');
    });

    it('has no key written in the configuration files', function () {
        $services = file_get_contents(config_path('services.php'));

        expect($services)->toContain("secret('TMDB_API_KEY')")
            ->and($services)->not->toMatch('/[0-9a-f]{32}/');
    });
});
