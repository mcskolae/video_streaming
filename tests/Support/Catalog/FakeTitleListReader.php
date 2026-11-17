<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use HomeFlix\Catalog\Application\Port\TitleListReader;
use HomeFlix\Catalog\Application\Query\AdminTitlePage;
use HomeFlix\Catalog\Domain\Model\TitleType;

final class FakeTitleListReader implements TitleListReader
{
    /** @param  list<int>  $importedMovies */
    public function __construct(public array $importedMovies = []) {}

    public function adminPage(int $page, int $perPage): AdminTitlePage
    {
        return new AdminTitlePage([], 0, $page, $perPage);
    }

    public function importedTmdbIds(TitleType $type, array $tmdbIds): array
    {
        return $type === TitleType::Movie ? array_values(array_intersect($tmdbIds, $this->importedMovies)) : [];
    }
}
