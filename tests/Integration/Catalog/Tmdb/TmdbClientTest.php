<?php

declare(strict_types=1);

use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Infrastructure\Tmdb\TmdbClient;
use HomeFlix\Catalog\Infrastructure\Tmdb\TmdbResourceNotFound;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const V3_KEY = '0123456789abcdef0123456789abcdef';
const READ_TOKEN = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJ0ZXN0In0.c2lnbmF0dXJl';
const TMDB_URL = 'https://api.themoviedb.org/3';

function tmdbClient(?string $credential = V3_KEY, string $baseUrl = TMDB_URL): TmdbClient
{
    return new TmdbClient(app(HttpFactory::class), app(Cache::class), $credential, $baseUrl);
}

beforeEach(function () {
    Sleep::fake();
    Http::preventStrayRequests();
});

describe('authentication', function () {
    it('sends a v3 API key as the api_key parameter', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 1])]);

        tmdbClient(V3_KEY)->get('/movie/1');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api_key='.V3_KEY)
            && ! $request->hasHeader('Authorization'));
    });

    it('sends a read access token as a bearer header and keeps it out of the URL', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 1])]);

        tmdbClient(READ_TOKEN)->get('/movie/1');

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer '.READ_TOKEN)
            && ! str_contains($request->url(), 'api_key')
            && ! str_contains($request->url(), READ_TOKEN));
    });

    it('asks for the configured language and an exact host', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 1])]);

        tmdbClient()->get('/movie/1', ['append' => 'x']);

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.themoviedb.org/3/movie/1?')
            && str_contains($request->url(), 'language=fr-FR')
            && str_contains($request->url(), 'append=x'));
    });

    it('does not call the network without a credential', function (?string $credential) {
        try {
            tmdbClient($credential)->get('/movie/1');
        } finally {
            Http::assertNothingSent();
        }
    })->with([[null], [''], ['   ']])->throws(MetadataProviderUnavailable::class, 'not configured');
});

describe('base URL guard', function () {
    it('refuses any base URL other than https://api.themoviedb.org', function (string $url) {
        tmdbClient(V3_KEY, $url);
    })->with([
        'plain http' => ['http://api.themoviedb.org/3'],
        'other host' => ['https://evil.example/3'],
        'look-alike suffix' => ['https://api.themoviedb.org.evil.example/3'],
        'userinfo trick' => ['https://api.themoviedb.org@evil.example/3'],
        'empty' => [''],
    ])->throws(LogicException::class);
});

describe('responses', function () {
    it('returns the decoded JSON', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 1, 'title' => 'A'])]);

        expect(tmdbClient()->get('/movie/1'))->toBe(['id' => 1, 'title' => 'A']);
    });

    it('turns a 404 into TmdbResourceNotFound', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['status_message' => 'not found'], 404)]);

        tmdbClient()->get('/movie/1');
    })->throws(TmdbResourceNotFound::class);

    it('turns a refused credential into an explicit error', function (int $status) {
        Http::fake(['api.themoviedb.org/*' => Http::response(['status_message' => 'Invalid API key'], $status)]);

        expect(fn () => tmdbClient()->get('/movie/1'))
            ->toThrow(MetadataProviderUnavailable::class, 'refused the credential');
    })->with([401, 403]);

    it('turns a rate limit into an explicit error and does not retry it', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response([], 429)]);

        expect(fn () => tmdbClient()->get('/movie/1'))->toThrow(MetadataProviderUnavailable::class, 'rate limit');
        Http::assertSentCount(1);
    });

    it('makes three attempts on a server error, then gives up', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response([], 500)]);

        expect(fn () => tmdbClient()->get('/movie/1'))->toThrow(MetadataProviderUnavailable::class, 'unexpected status (500)');
        Http::assertSentCount(3);
    });

    it('recovers when a retry succeeds', function () {
        Http::fake(['api.themoviedb.org/*' => Http::sequence()->pushStatus(503)->push(['id' => 7])]);

        expect(tmdbClient()->get('/movie/7'))->toBe(['id' => 7]);
        Http::assertSentCount(2);
    });

    it('does not retry a client error', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response([], 422)]);

        expect(fn () => tmdbClient()->get('/movie/1'))->toThrow(MetadataProviderUnavailable::class, 'unexpected status (422)');
        Http::assertSentCount(1);
    });

    it('refuses a body that is not a JSON object or array', function (string $body) {
        Http::fake(['api.themoviedb.org/*' => Http::response($body, 200)]);

        expect(fn () => tmdbClient()->get('/movie/1'))->toThrow(MetadataProviderUnavailable::class, 'could not be understood');
    })->with(['<html>maintenance</html>', 'null', '"texte"', '42', '']);
});

describe('credential never leaks', function () {
    it('keeps the credential and the URL out of the error when the connection fails', function (string $credential) {
        Http::fake(fn () => throw new ConnectionException(
            "cURL error 28: Operation timed out for https://api.themoviedb.org/3/movie/1?api_key={$credential}",
        ));

        try {
            tmdbClient($credential)->get('/movie/1');
            $this->fail('An exception was expected.');
        } catch (MetadataProviderUnavailable $exception) {
            expect($exception->getMessage())->not->toContain($credential)
                ->and($exception->getMessage())->not->toContain('api.themoviedb.org')
                ->and($exception->getPrevious())->toBeNull()
                ->and($exception->getTraceAsString())->not->toContain($credential);
        }
    })->with([[V3_KEY], [READ_TOKEN]]);

    it('retries a connection failure', function () {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        expect(fn () => tmdbClient()->get('/movie/1'))->toThrow(MetadataProviderUnavailable::class, 'could not be reached');
    });

    it('keeps the credential out of every error message', function (int $status) {
        Http::fake(['api.themoviedb.org/*' => Http::response(['status_message' => 'echo '.V3_KEY], $status)]);

        try {
            tmdbClient()->get('/movie/1');
        } catch (Throwable $exception) {
            expect($exception->getMessage())->not->toContain(V3_KEY);
        }
    })->with([401, 403, 404, 429, 500]);
});

describe('cache', function () {
    it('serves a repeated request from the cache', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 1])]);
        $client = tmdbClient();

        $client->get('/movie/1');
        $client->get('/movie/1');

        Http::assertSentCount(1);
    });

    it('does not mix up different resources or queries', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 1])]);
        $client = tmdbClient();

        $client->get('/movie/1');
        $client->get('/movie/2');
        $client->get('/search/movie', ['query' => 'a']);
        $client->get('/search/movie', ['query' => 'b']);

        Http::assertSentCount(4);
    });

    it('does not depend on the order of the query parameters', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 1])]);
        $client = tmdbClient();

        $client->get('/search/movie', ['query' => 'a', 'page' => 1]);
        $client->get('/search/movie', ['page' => 1, 'query' => 'a']);

        Http::assertSentCount(1);
    });

    it('does not cache failures', function () {
        Http::fake(['api.themoviedb.org/*' => Http::sequence()->pushStatus(404)->push(['id' => 1])]);
        $client = tmdbClient();

        expect(fn () => $client->get('/movie/1'))->toThrow(TmdbResourceNotFound::class);
        expect($client->get('/movie/1'))->toBe(['id' => 1]);
        Http::assertSentCount(2);
    });

    it('expires entries after the requested time', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['id' => 1])]);
        $client = tmdbClient();

        $client->get('/movie/1', cacheSeconds: 60);
        $this->travel(61)->seconds();
        $client->get('/movie/1', cacheSeconds: 60);

        Http::assertSentCount(2);
    });
});
