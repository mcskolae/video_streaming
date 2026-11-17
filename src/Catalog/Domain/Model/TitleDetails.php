<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Model;

use InvalidArgumentException;

/** Informations descriptives d'un titre, telles qu'elles viennent de TMDB. */
final readonly class TitleDetails
{
    public string $name;

    public ?string $originalName;

    /** @var list<Genre> */
    public array $genres;

    /** @param  list<Genre>  $genres */
    public function __construct(
        string $name,
        ?string $originalName = null,
        public string $overview = '',
        public ?int $releaseYear = null,
        public ?int $runtimeMinutes = null,
        public ?string $posterPath = null,
        public ?string $backdropPath = null,
        array $genres = [],
    ) {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 255) {
            throw new InvalidArgumentException('A title name must be 1 to 255 characters long.');
        }

        $originalName = $originalName === null ? null : trim($originalName);

        if ($originalName !== null && ($originalName === '' || mb_strlen($originalName) > 255)) {
            throw new InvalidArgumentException('An original title must be 1 to 255 characters long when given.');
        }

        if ($releaseYear !== null && ($releaseYear < 1870 || $releaseYear > 2100)) {
            throw new InvalidArgumentException(sprintf('Release year %d is out of range (1870 to 2100).', $releaseYear));
        }

        if ($runtimeMinutes !== null && ($runtimeMinutes < 1 || $runtimeMinutes > 10000)) {
            throw new InvalidArgumentException(sprintf('Runtime %d is out of range (1 to 10000 minutes).', $runtimeMinutes));
        }

        $this->name = $name;
        $this->originalName = $originalName;
        $this->genres = self::uniqueGenres($genres);
    }

    /**
     * @param  list<Genre>  $genres
     * @return list<Genre>
     */
    private static function uniqueGenres(array $genres): array
    {
        $byTmdbId = [];

        foreach ($genres as $genre) {
            $byTmdbId[$genre->tmdbId] ??= $genre;
        }

        return array_values($byTmdbId);
    }
}
