<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Command;

/** Importe un film depuis TMDB, ou met à jour sa fiche s'il est déjà au catalogue. */
final readonly class ImportMovieFromTmdb
{
    public function __construct(public int $tmdbId) {}
}
