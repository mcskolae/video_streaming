<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Metadata;

use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;

/** Une ligne de résultat de recherche : de quoi choisir un titre avant de l'importer. */
final readonly class SearchResult
{
    public function __construct(
        public TmdbId $tmdbId,
        public TitleType $type,
        public string $name,
        public ?string $originalName,
        public string $overview,
        public ?int $releaseYear,
        public ?string $posterPath,
    ) {}
}
