<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Metadata;

use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TmdbId;

final readonly class MovieMetadata
{
    public function __construct(
        public TmdbId $tmdbId,
        public TitleDetails $details,
    ) {}
}
