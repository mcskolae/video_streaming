<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

use HomeFlix\Catalog\Application\Metadata\SearchResult;

final readonly class TmdbSearchHit
{
    public function __construct(
        public SearchResult $result,
        /** Ce film est déjà au catalogue. */
        public bool $imported,
    ) {}
}
