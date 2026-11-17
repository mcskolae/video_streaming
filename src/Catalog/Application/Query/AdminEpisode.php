<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

final readonly class AdminEpisode
{
    public function __construct(
        public string $id,
        public int $number,
        public string $name,
        public bool $playable,
    ) {}
}
