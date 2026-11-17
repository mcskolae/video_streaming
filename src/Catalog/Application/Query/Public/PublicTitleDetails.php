<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query\Public;

use HomeFlix\Catalog\Domain\Model\TitleType;

/** La fiche complète d'un titre publié : données de lecture, pas l'agrégat. */
final readonly class PublicTitleDetails
{
    /**
     * @param  list<PublicGenre>  $genres
     * @param  list<PublicSeason>  $seasons  vide pour un film
     */
    public function __construct(
        public string $slug,
        public TitleType $type,
        public string $name,
        public ?string $originalName,
        public string $overview,
        public ?int $releaseYear,
        public ?int $runtimeMinutes,
        public ?string $posterPath,
        public ?string $backdropPath,
        public array $genres,
        public array $seasons,
    ) {}
}
