<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Port;

use HomeFlix\Catalog\Application\Query\Public\PublicGenre;
use HomeFlix\Catalog\Application\Query\Public\PublicSitemapEntry;
use HomeFlix\Catalog\Application\Query\Public\PublicTitleCard;
use HomeFlix\Catalog\Application\Query\Public\PublicTitleDetails;
use HomeFlix\Catalog\Application\Query\Public\PublicTitlePage;

/**
 * Port de lecture du catalogue public.
 *
 * Contrat : seuls les titres PUBLIÉS sont jamais retournés. Un brouillon est invisible partout :
 * dans les listes, les genres, la recherche et la fiche.
 */
interface PublicCatalogReader
{
    /**
     * Derniers titres publiés, du plus récent au plus ancien.
     *
     * @return list<PublicTitleCard>
     */
    public function latest(int $limit): array;

    /**
     * Genres qui ont au moins un titre publié, par ordre alphabétique.
     *
     * @return list<PublicGenre>
     */
    public function genres(): array;

    /** Un genre, s'il a au moins un titre publié. */
    public function genre(int $tmdbId): ?PublicGenre;

    /** Titres publiés d'un genre, les plus récents d'abord. Les pages commencent à 1. */
    public function byGenre(int $genreTmdbId, int $page, int $perPage): PublicTitlePage;

    /**
     * Recherche tolérante aux fautes de frappe et aux accents, la plus proche d'abord.
     *
     * @return list<PublicTitleCard>
     */
    public function search(string $term, int $limit): array;

    public function bySlug(string $slug): ?PublicTitleDetails;

    /**
     * Titres publiés pour le plan du site, les plus récemment publiés d'abord.
     *
     * @return list<PublicSitemapEntry>
     */
    public function sitemap(int $limit): array;
}
