<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Query;

final readonly class UserPage
{
    /** @param  list<UserRow>  $rows */
    public function __construct(
        public array $rows,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
