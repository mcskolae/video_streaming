<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query\Public;

final readonly class PublicGenre
{
    public function __construct(
        public int $tmdbId,
        public string $name,
        /** Nombre de titres publiés dans ce genre. */
        public int $titleCount,
    ) {}
}
