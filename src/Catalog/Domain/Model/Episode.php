<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Model;

use InvalidArgumentException;

/** Épisode d'une saison. Ne vit qu'à l'intérieur de l'agrégat Title. */
final class Episode
{
    public readonly string $name;

    public function __construct(
        public readonly EpisodeId $id,
        public readonly int $number,
        string $name,
        public readonly string $overview = '',
        public readonly ?int $runtimeMinutes = null,
        public readonly ?string $stillPath = null,
        private bool $playable = false,
    ) {
        $name = trim($name);

        if ($number < 1) {
            throw new InvalidArgumentException('An episode number starts at 1.');
        }

        if (mb_strlen($name) > 255) {
            throw new InvalidArgumentException('An episode name is 255 characters long at most.');
        }

        if ($runtimeMinutes !== null && ($runtimeMinutes < 1 || $runtimeMinutes > 10000)) {
            throw new InvalidArgumentException('An episode runtime is 1 to 10000 minutes.');
        }

        $this->name = $name;
    }

    public function isPlayable(): bool
    {
        return $this->playable;
    }

    public function markPlayable(): void
    {
        $this->playable = true;
    }

    public function markUnplayable(): void
    {
        $this->playable = false;
    }
}
