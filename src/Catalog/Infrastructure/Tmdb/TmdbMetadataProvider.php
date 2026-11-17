<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Tmdb;

use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Application\Metadata\MovieMetadata;
use HomeFlix\Catalog\Application\Metadata\SeriesMetadata;
use HomeFlix\Catalog\Application\Port\MetadataProvider;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use InvalidArgumentException;

/** Adaptateur TMDB du port MetadataProvider. */
final readonly class TmdbMetadataProvider implements MetadataProvider
{
    private const SEARCH_CACHE_SECONDS = 600;

    private const DETAILS_CACHE_SECONDS = 86400;

    public function __construct(private TmdbClient $client) {}

    public function search(string $query, TitleType $type): array
    {
        $query = mb_substr(trim($query), 0, 200);

        if ($query === '') {
            return [];
        }

        try {
            $payload = $this->client->get(
                $type === TitleType::Movie ? '/search/movie' : '/search/tv',
                ['query' => $query, 'include_adult' => 'false', 'page' => 1],
                self::SEARCH_CACHE_SECONDS,
            );
        } catch (TmdbResourceNotFound) {
            throw MetadataProviderUnavailable::unexpectedStatus(404);
        }

        return TmdbMapper::searchResults($payload, $type);
    }

    public function movie(TmdbId $id): MovieMetadata
    {
        try {
            $data = $this->client->get("/movie/{$id->value}", [], self::DETAILS_CACHE_SECONDS);
        } catch (TmdbResourceNotFound) {
            throw MetadataNotFound::forTitle(TitleType::Movie, $id);
        }

        try {
            return TmdbMapper::movie($data);
        } catch (InvalidArgumentException) {
            throw MetadataProviderUnavailable::unexpectedResponse();
        }
    }

    public function series(TmdbId $id): SeriesMetadata
    {
        try {
            $data = $this->client->get("/tv/{$id->value}", [], self::DETAILS_CACHE_SECONDS);
        } catch (TmdbResourceNotFound) {
            throw MetadataNotFound::forTitle(TitleType::Series, $id);
        }

        $seasonDetails = [];

        foreach (TmdbMapper::seasonNumbers($data) as $number) {
            try {
                $seasonDetails[$number] = $this->client->get("/tv/{$id->value}/season/{$number}", [], self::DETAILS_CACHE_SECONDS);
            } catch (TmdbResourceNotFound) {
                // TMDB liste une saison dont il n'a pas le détail : on la garde sans épisode.
                $seasonDetails[$number] = null;
            }
        }

        try {
            return TmdbMapper::series($data, $seasonDetails);
        } catch (InvalidArgumentException) {
            throw MetadataProviderUnavailable::unexpectedResponse();
        }
    }
}
