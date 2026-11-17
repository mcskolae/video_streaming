<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

final readonly class AdminSeason
{
    /** @param  list<AdminEpisode>  $episodes */
    public function __construct(
        public int $number,
        public string $name,
        public array $episodes,
    ) {}
}
