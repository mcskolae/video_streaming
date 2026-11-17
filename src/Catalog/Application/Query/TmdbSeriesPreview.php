<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

use HomeFlix\Catalog\Application\Metadata\SeriesMetadata;

final readonly class TmdbSeriesPreview
{
    public function __construct(
        public SeriesMetadata $series,
        public bool $imported,
    ) {}

    public function episodeCount(): int
    {
        return array_sum(array_map(fn ($season) => count($season->episodes), $this->series->seasons));
    }
}
