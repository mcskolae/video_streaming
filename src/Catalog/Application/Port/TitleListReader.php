<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Port;

use HomeFlix\Catalog\Application\Query\AdminSeries;
use HomeFlix\Catalog\Application\Query\AdminTitlePage;
use HomeFlix\Catalog\Domain\Model\TitleType;

/**
 * Port de lecture : les listes se lisent directement, sans charger les agrégats.
 */
interface TitleListReader
{
    /** Tous les titres, les plus récents d'abord. Les pages commencent à 1. */
    public function adminPage(int $page, int $perPage): AdminTitlePage;

    /**
     * Parmi ces identifiants TMDB, ceux qui sont déjà au catalogue.
     *
     * @param  list<int>  $tmdbIds
     * @return list<int>
     */
    public function importedTmdbIds(TitleType $type, array $tmdbIds): array;

    /** Une série avec ses saisons et ses épisodes, ou null si l'identifiant n'est pas celui d'une série. */
    public function adminSeries(string $titleId): ?AdminSeries;
}
