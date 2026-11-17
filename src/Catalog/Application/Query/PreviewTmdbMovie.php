<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

final readonly class PreviewTmdbMovie
{
    public function __construct(public int $tmdbId) {}
}
