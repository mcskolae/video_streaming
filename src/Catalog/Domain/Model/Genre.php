<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Model;

use InvalidArgumentException;

/** Genre TMDB. Deux genres sont identiques s'ils ont le même identifiant TMDB. */
final readonly class Genre
{
    public string $name;

    public function __construct(public int $tmdbId, string $name)
    {
        $name = trim($name);

        if ($tmdbId < 1) {
            throw new InvalidArgumentException('A genre TMDB id must be positive.');
        }

        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('A genre name must be 1 to 100 characters long.');
        }

        $this->name = $name;
    }
}
