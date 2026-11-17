<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Port;

use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Application\Metadata\MovieMetadata;
use HomeFlix\Catalog\Application\Metadata\SearchResult;
use HomeFlix\Catalog\Application\Metadata\SeriesMetadata;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;

/**
 * Port sortant : source des métadonnées d'un film ou d'une série (affiche, synopsis, genres,
 * saisons, épisodes). L'application ne connaît ni TMDB ni HTTP.
 */
interface MetadataProvider
{
    /**
     * Cherche des titres par leur nom. Une recherche vide retourne une liste vide.
     *
     * @return list<SearchResult>
     *
     * @throws MetadataProviderUnavailable
     */
    public function search(string $query, TitleType $type): array;

    /**
     * @throws MetadataNotFound
     * @throws MetadataProviderUnavailable
     */
    public function movie(TmdbId $id): MovieMetadata;

    /**
     * Une série avec toutes ses saisons et tous leurs épisodes.
     *
     * @throws MetadataNotFound
     * @throws MetadataProviderUnavailable
     */
    public function series(TmdbId $id): SeriesMetadata;
}
