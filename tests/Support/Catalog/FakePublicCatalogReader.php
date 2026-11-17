<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use HomeFlix\Catalog\Application\Port\PublicCatalogReader;
use HomeFlix\Catalog\Application\Query\Public\PublicGenre;
use HomeFlix\Catalog\Application\Query\Public\PublicTitleDetails;
use HomeFlix\Catalog\Application\Query\Public\PublicTitlePage;

/** Enregistre les arguments reçus, pour vérifier ce que le service demande au port. */
final class FakePublicCatalogReader implements PublicCatalogReader
{
    /** @var list<array{string, array<int, mixed>}> */
    public array $calls = [];

    public ?PublicGenre $genre = null;

    public function latest(int $limit): array
    {
        $this->calls[] = ['latest', [$limit]];

        return [];
    }

    public function genres(): array
    {
        $this->calls[] = ['genres', []];

        return [];
    }

    public function genre(int $tmdbId): ?PublicGenre
    {
        $this->calls[] = ['genre', [$tmdbId]];

        return $this->genre;
    }

    public function byGenre(int $genreTmdbId, int $page, int $perPage): PublicTitlePage
    {
        $this->calls[] = ['byGenre', [$genreTmdbId, $page, $perPage]];

        return new PublicTitlePage([], 0, $page, $perPage);
    }

    public function search(string $term, int $limit): array
    {
        $this->calls[] = ['search', [$term, $limit]];

        return [];
    }

    public function bySlug(string $slug): ?PublicTitleDetails
    {
        $this->calls[] = ['bySlug', [$slug]];

        return null;
    }
}
