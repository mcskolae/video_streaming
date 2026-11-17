<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use HomeFlix\Catalog\Application\Port\TitleListReader;
use HomeFlix\Catalog\Application\Query\AdminSeries;
use HomeFlix\Catalog\Application\Query\AdminTitlePage;
use HomeFlix\Catalog\Domain\Model\TitleType;

final class FakeTitleListReader implements TitleListReader
{
    /**
     * @param  list<int>  $importedMovies
     * @param  list<int>  $importedSeries
     */
    public function __construct(public array $importedMovies = [], public array $importedSeries = []) {}

    public function adminPage(int $page, int $perPage): AdminTitlePage
    {
        return new AdminTitlePage([], 0, $page, $perPage);
    }

    public function importedTmdbIds(TitleType $type, array $tmdbIds): array
    {
        return array_values(array_intersect($tmdbIds, $type === TitleType::Movie ? $this->importedMovies : $this->importedSeries));
    }

    public function adminSeries(string $titleId): ?AdminSeries
    {
        return null;
    }
}
