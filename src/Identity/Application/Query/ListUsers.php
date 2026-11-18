<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Query;

final readonly class ListUsers
{
    public function __construct(public int $page = 1) {}
}
