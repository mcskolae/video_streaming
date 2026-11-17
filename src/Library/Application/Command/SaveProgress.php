<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Command;

final readonly class SaveProgress
{
    public function __construct(
        public string $memberId,
        public string $mediaId,
        public float $positionSeconds,
    ) {}
}
