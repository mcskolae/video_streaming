<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

use HomeFlix\Catalog\Domain\Model\TitleStatus;
use HomeFlix\Catalog\Domain\Model\TitleType;

/** Une ligne de la liste d'administration : données de lecture, pas l'agrégat. */
final readonly class AdminTitleRow
{
    public function __construct(
        public string $id,
        public TitleType $type,
        public int $tmdbId,
        public string $name,
        public ?int $releaseYear,
        public ?string $posterPath,
        public TitleStatus $status,
        /** Un média est prêt (film) ou au moins un épisode l'est (série). */
        public bool $playable,
    ) {}
}
