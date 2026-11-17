<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use HomeFlix\Shared\Domain\Clock;

final readonly class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $now = new DateTimeImmutable('2026-11-17 12:00:00')) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
