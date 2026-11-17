<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Model;

use HomeFlix\Catalog\Domain\Exception\EpisodeAlreadyExists;
use HomeFlix\Catalog\Domain\Exception\EpisodeNotFound;
use InvalidArgumentException;

/** Saison d'une série. Le numéro 0 désigne les épisodes spéciaux, comme chez TMDB. */
final class Season
{
    public readonly string $name;

    /** @var array<int, Episode> épisodes indexés par numéro */
    private array $episodes = [];

    /** @param  list<Episode>  $episodes */
    public function __construct(
        public readonly SeasonId $id,
        public readonly int $number,
        string $name = '',
        public readonly string $overview = '',
        array $episodes = [],
    ) {
        $name = trim($name);

        if ($number < 0 || $number > 999) {
            throw new InvalidArgumentException('A season number is 0 to 999.');
        }

        if (mb_strlen($name) > 255) {
            throw new InvalidArgumentException('A season name is 255 characters long at most.');
        }

        $this->name = $name;

        foreach ($episodes as $episode) {
            $this->addEpisode($episode);
        }
    }

    public function addEpisode(Episode $episode): void
    {
        if (isset($this->episodes[$episode->number])) {
            throw EpisodeAlreadyExists::inSeason($this->number, $episode->number);
        }

        $this->episodes[$episode->number] = $episode;
        ksort($this->episodes);
    }

    public function episode(int $number): Episode
    {
        return $this->episodes[$number] ?? throw EpisodeNotFound::inSeason($this->number, $number);
    }

    /** @return list<Episode> */
    public function episodes(): array
    {
        return array_values($this->episodes);
    }

    public function hasPlayableEpisode(): bool
    {
        foreach ($this->episodes as $episode) {
            if ($episode->isPlayable()) {
                return true;
            }
        }

        return false;
    }
}
