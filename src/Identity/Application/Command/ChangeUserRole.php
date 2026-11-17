<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Command;

final readonly class ChangeUserRole
{
    public function __construct(
        public string $userId,
        public string $role,
    ) {}
}
