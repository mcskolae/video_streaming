<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query\Public;

use HomeFlix\Catalog\Domain\Model\TitleType;

/** Un titre publié, tel qu'il apparaît dans une grille. */
final readonly class PublicTitleCard
{
    public function __construct(
        public string $slug,
        public TitleType $type,
        public string $name,
        public ?int $releaseYear,
        public ?string $posterPath,
    ) {}
}
