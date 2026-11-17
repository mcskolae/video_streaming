<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

final readonly class ListAdminTitles
{
    public const DEFAULT_PER_PAGE = 20;

    public int $page;

    public int $perPage;

    public function __construct(int $page = 1, int $perPage = self::DEFAULT_PER_PAGE)
    {
        $this->page = max(1, $page);
        $this->perPage = min(100, max(1, $perPage));
    }
}
