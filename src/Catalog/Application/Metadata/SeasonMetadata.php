<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Metadata;

final readonly class SeasonMetadata
{
    /** @param  list<EpisodeMetadata>  $episodes */
    public function __construct(
        public int $number,
        public string $name,
        public string $overview,
        public array $episodes,
    ) {}
}
