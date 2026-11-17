<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Repository;

use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleId;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;

/** Port de persistance de l'agrégat Title. */
interface TitleRepository
{
    /** Crée ou met à jour le titre avec ses genres, saisons et épisodes. */
    public function save(Title $title): void;

    public function find(TitleId $id): ?Title;

    public function findByTmdbId(TitleType $type, TmdbId $tmdbId): ?Title;

    public function findBySlug(Slug $slug): ?Title;
}
