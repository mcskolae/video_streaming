<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Command;

final readonly class SuspendUser
{
    public function __construct(public string $userId) {}
}
