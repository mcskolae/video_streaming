<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

final readonly class PreviewTmdbSeries
{
    public function __construct(public int $tmdbId) {}
}
