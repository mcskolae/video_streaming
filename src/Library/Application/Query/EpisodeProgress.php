<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Query;

/** Où le membre en est dans une vidéo d'un titre (film ou épisode). */
final readonly class EpisodeProgress
{
    public function __construct(
        public int $positionSeconds,
        public int $durationSeconds,
        public bool $completed,
    ) {}

    public function percent(): int
    {
        return $this->completed ? 100 : (int) floor($this->positionSeconds * 100 / max(1, $this->durationSeconds));
    }
}
