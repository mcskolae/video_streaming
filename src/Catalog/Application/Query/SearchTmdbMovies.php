<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

use HomeFlix\Catalog\Domain\Model\TitleType;

final readonly class SearchTmdbMovies
{
    public function __construct(
        public string $query,
        public TitleType $type = TitleType::Movie,
    ) {}
}
