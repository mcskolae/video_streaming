<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

use HomeFlix\Catalog\Application\Metadata\MovieMetadata;

final readonly class TmdbMoviePreview
{
    public function __construct(
        public MovieMetadata $movie,
        public bool $imported,
    ) {}
}
