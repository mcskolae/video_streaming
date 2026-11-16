<?php

declare(strict_types=1);

namespace HomeFlix\Shared\Infrastructure;

use HomeFlix\Shared\Application\IdGenerator;
use Ramsey\Uuid\Uuid;

/** UUID v7 : triable par date de création, ce qui est favorable aux index PostgreSQL. */
final class RamseyIdGenerator implements IdGenerator
{
    public function generate(): string
    {
        return Uuid::uuid7()->toString();
    }
}
