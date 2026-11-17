<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Query;

/**
 * Un favori tel qu'il s'affiche : mêmes propriétés qu'une fiche du catalogue public,
 * pour que la même grille puisse l'afficher.
 */
final readonly class FavoriteCard
{
    public function __construct(
        public string $slug,
        public FavoriteKind $type,
        public string $name,
        public ?int $releaseYear,
        public ?string $posterPath,
    ) {}
}
