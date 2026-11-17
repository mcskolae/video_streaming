<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

final readonly class SearchTmdbMovies
{
    public function __construct(public string $query) {}
}
