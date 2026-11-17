<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Query;

use HomeFlix\Identity\Application\Port\UserListReader;

final readonly class ListUsersHandler
{
    public const PER_PAGE = 25;

    public function __construct(private UserListReader $reader) {}

    public function handle(ListUsers $query): UserPage
    {
        return $this->reader->page(max(1, $query->page), self::PER_PAGE);
    }
}
