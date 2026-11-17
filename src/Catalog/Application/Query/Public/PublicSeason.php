<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query\Public;

final readonly class PublicSeason
{
    /** @param  list<PublicEpisode>  $episodes */
    public function __construct(
        public int $number,
        public string $name,
        public string $overview,
        public array $episodes,
    ) {}
}
