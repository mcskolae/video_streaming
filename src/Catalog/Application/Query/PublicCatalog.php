<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

use HomeFlix\Catalog\Application\Port\PublicCatalogReader;
use HomeFlix\Catalog\Application\Query\Public\PublicGenre;
use HomeFlix\Catalog\Application\Query\Public\PublicSitemapEntry;
use HomeFlix\Catalog\Application\Query\Public\PublicTitleCard;
use HomeFlix\Catalog\Application\Query\Public\PublicTitleDetails;
use HomeFlix\Catalog\Application\Query\Public\PublicTitlePage;

/**
 * Requêtes du catalogue public. Elle normalise les entrées venant du navigateur
 * (terme de recherche, numéro de page) avant d'interroger le port de lecture.
 */
final readonly class PublicCatalog
{
    public const HOME_LATEST = 12;

    public const PER_PAGE = 24;

    public const MAX_SEARCH_RESULTS = 48;

    public const MAX_TERM_LENGTH = 100;

    /** Le protocole sitemap limite un fichier à 50 000 adresses ; on garde de la place pour l'accueil et les genres. */
    public const MAX_SITEMAP_TITLES = 49_000;

    public function __construct(private PublicCatalogReader $reader) {}

    /** @return list<PublicTitleCard> */
    public function latest(): array
    {
        return $this->reader->latest(self::HOME_LATEST);
    }

    /** @return list<PublicGenre> */
    public function genres(): array
    {
        return $this->reader->genres();
    }

    public function genre(int $tmdbId): ?PublicGenre
    {
        return $tmdbId < 1 ? null : $this->reader->genre($tmdbId);
    }

    public function byGenre(int $genreTmdbId, int $page): PublicTitlePage
    {
        return $this->reader->byGenre($genreTmdbId, max(1, $page), self::PER_PAGE);
    }

    /**
     * Un terme est nettoyé : espaces superflus supprimés, caractères de contrôle retirés,
     * longueur limitée. Un terme vide ne déclenche aucune recherche.
     *
     * @return list<PublicTitleCard>
     */
    public function search(string $term): array
    {
        $term = self::normalizeTerm($term);

        return $term === '' ? [] : $this->reader->search($term, self::MAX_SEARCH_RESULTS);
    }

    public function show(string $slug): ?PublicTitleDetails
    {
        return $this->reader->bySlug($slug);
    }

    /** @return list<PublicSitemapEntry> */
    public function sitemap(): array
    {
        return $this->reader->sitemap(self::MAX_SITEMAP_TITLES);
    }

    public static function normalizeTerm(string $term): string
    {
        $term = (string) preg_replace('/[\p{C}]+/u', ' ', $term);
        $term = trim((string) preg_replace('/\s+/u', ' ', $term));

        return mb_substr($term, 0, self::MAX_TERM_LENGTH);
    }
}
