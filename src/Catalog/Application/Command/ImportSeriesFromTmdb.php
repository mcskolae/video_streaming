<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Command;

/** Importe une série depuis TMDB avec ses saisons et ses épisodes, ou la met à jour si elle est déjà au catalogue. */
final readonly class ImportSeriesFromTmdb
{
    public function __construct(public int $tmdbId) {}
}
