<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

final readonly class AdminTitlePage
{
    /** @param  list<AdminTitleRow>  $rows */
    public function __construct(
        public array $rows,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
