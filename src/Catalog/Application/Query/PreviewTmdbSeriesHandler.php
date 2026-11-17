<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Application\Port\MetadataProvider;
use HomeFlix\Catalog\Application\Port\TitleListReader;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use InvalidArgumentException;

final readonly class PreviewTmdbSeriesHandler
{
    public function __construct(
        private MetadataProvider $metadata,
        private TitleListReader $reader,
    ) {}

    /**
     * @throws InvalidArgumentException si l'identifiant TMDB n'est pas valide
     * @throws MetadataNotFound
     * @throws MetadataProviderUnavailable
     */
    public function handle(PreviewTmdbSeries $query): TmdbSeriesPreview
    {
        $tmdbId = new TmdbId($query->tmdbId);

        return new TmdbSeriesPreview(
            $this->metadata->series($tmdbId),
            $this->reader->importedTmdbIds(TitleType::Series, [$tmdbId->value]) !== [],
        );
    }
}
