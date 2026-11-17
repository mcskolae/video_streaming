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

final readonly class PreviewTmdbMovieHandler
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
    public function handle(PreviewTmdbMovie $query): TmdbMoviePreview
    {
        $tmdbId = new TmdbId($query->tmdbId);

        return new TmdbMoviePreview(
            $this->metadata->movie($tmdbId),
            $this->reader->importedTmdbIds(TitleType::Movie, [$tmdbId->value]) !== [],
        );
    }
}
