<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Application\Port\MetadataProvider;
use HomeFlix\Catalog\Application\Port\TitleListReader;

final readonly class SearchTmdbMoviesHandler
{
    public function __construct(
        private MetadataProvider $metadata,
        private TitleListReader $reader,
    ) {}

    /**
     * @return list<TmdbSearchHit>
     *
     * @throws MetadataProviderUnavailable
     */
    public function handle(SearchTmdbMovies $query): array
    {
        $results = $this->metadata->search($query->query, $query->type);

        $imported = array_flip($this->reader->importedTmdbIds(
            $query->type,
            array_map(fn ($result) => $result->tmdbId->value, $results),
        ));

        return array_map(
            fn ($result) => new TmdbSearchHit($result, isset($imported[$result->tmdbId->value])),
            $results,
        );
    }
}
