<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

use HomeFlix\Catalog\Application\Port\TitleListReader;

final readonly class ListAdminTitlesHandler
{
    public function __construct(private TitleListReader $reader) {}

    public function handle(ListAdminTitles $query): AdminTitlePage
    {
        return $this->reader->adminPage($query->page, $query->perPage);
    }
}
