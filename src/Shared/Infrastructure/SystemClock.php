<?php

declare(strict_types=1);

namespace HomeFlix\Shared\Infrastructure;

use DateTimeImmutable;
use HomeFlix\Shared\Domain\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
