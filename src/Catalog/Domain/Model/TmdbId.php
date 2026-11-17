<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Model;

use InvalidArgumentException;

/** Identifiant d'un film ou d'une série chez TMDB. */
final readonly class TmdbId
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidArgumentException(sprintf('A TMDB id must be positive, got %d.', $value));
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
