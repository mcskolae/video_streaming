<?php

declare(strict_types=1);

namespace Tests\Support;

use HomeFlix\Shared\Application\IdGenerator;
use Ramsey\Uuid\Uuid;

final class UuidV7Generator implements IdGenerator
{
    public function generate(): string
    {
        return Uuid::uuid7()->toString();
    }
}
