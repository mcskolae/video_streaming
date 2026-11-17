<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query\Public;

final readonly class PublicTitlePage
{
    /** @param  list<PublicTitleCard>  $cards */
    public function __construct(
        public array $cards,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
