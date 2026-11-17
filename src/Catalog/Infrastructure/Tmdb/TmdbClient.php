<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Tmdb;

use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;
use LogicException;
use Throwable;

/**
 * Client HTTP de l'API TMDB v3 : authentification, limites de temps, nouvelles tentatives,
 * cache et traduction des erreurs.
 *
 * Sécurité :
 *  - l'hôte est figé : une configuration qui pointerait ailleurs fait échouer la construction ;
 *  - la clé n'apparaît jamais dans un message d'exception (l'exception d'origine n'est pas chaînée,
 *    car elle contient l'URL, donc la clé quand elle passe en paramètre) ;
 *  - on accepte le jeton d'accès en lecture (en-tête Authorization, à préférer) ou la clé d'API v3
 *    (paramètre `api_key`).
 */
final readonly class TmdbClient
{
    private const ALLOWED_HOST = 'api.themoviedb.org';

    public function __construct(
        private Http $http,
        private Cache $cache,
        #[\SensitiveParameter] private ?string $credential,
        private string $baseUrl,
        private string $language = 'fr-FR',
    ) {
        $parts = parse_url($baseUrl);

        if (($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? null) !== self::ALLOWED_HOST) {
            throw new LogicException(sprintf('The TMDB base URL must be https://%s, got "%s".', self::ALLOWED_HOST, $baseUrl));
        }
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     *
     * @throws TmdbResourceNotFound
     * @throws MetadataProviderUnavailable
     */
    public function get(string $path, array $query = [], int $cacheSeconds = 86400): array
    {
        $credential = trim((string) $this->credential);

        if ($credential === '') {
            throw MetadataProviderUnavailable::notConfigured();
        }

        $query = ['language' => $this->language] + $query;
        ksort($query);

        // La clé de cache ne contient pas l'identifiant d'accès.
        $cacheKey = 'tmdb:'.sha1($path.'?'.http_build_query($query));
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            /** @var array<string, mixed> $cached */
            return $cached;
        }

        $data = $this->fetch($path, $query, $credential);

        // Seules les réponses réussies sont mises en cache.
        $this->cache->put($cacheKey, $data, $cacheSeconds);

        return $data;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function fetch(string $path, array $query, #[\SensitiveParameter] string $credential): array
    {
        $request = $this->http
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(5)
            // Trois tentatives au total (le premier argument est un nombre de tentatives, pas de relances).
            ->retry(
                3,
                300,
                fn (Throwable $exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
                throw: false,
            );

        // Un jeton d'accès en lecture est un JWT (il contient des points) ; une clé v3 est hexadécimale.
        if (str_contains($credential, '.')) {
            $request = $request->withToken($credential);
        } else {
            $query['api_key'] = $credential;
        }

        try {
            $response = $request->get($path, $query);
        } catch (ConnectionException) {
            // Volontairement non chaînée : le message d'origine contient l'URL complète.
            throw MetadataProviderUnavailable::unreachable();
        }

        $status = $response->status();

        if ($status === 404) {
            throw new TmdbResourceNotFound(sprintf('TMDB answered 404 for %s.', $path));
        }

        if ($status === 401 || $status === 403) {
            throw MetadataProviderUnavailable::invalidCredentials();
        }

        if ($status === 429) {
            throw MetadataProviderUnavailable::rateLimited();
        }

        if (! $response->successful()) {
            throw MetadataProviderUnavailable::unexpectedStatus($status);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw MetadataProviderUnavailable::unexpectedResponse();
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
