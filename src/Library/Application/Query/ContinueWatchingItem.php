<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Query;

/** Une ligne de « Reprendre la lecture ». */
final readonly class ContinueWatchingItem
{
    public function __construct(
        public string $slug,
        public FavoriteKind $type,
        public string $name,
        public ?int $releaseYear,
        public ?string $posterPath,
        public string $mediaId,
        /** Pour une série : « 2x5 », sinon null. */
        public ?string $episodeLabel,
        public int $positionSeconds,
        public int $durationSeconds,
        /** Vrai si l'épisode précédent est terminé : on propose la suite, pas une reprise. */
        public bool $isNext = false,
    ) {}

    public function percent(): int
    {
        return (int) floor($this->positionSeconds * 100 / max(1, $this->durationSeconds));
    }
}
