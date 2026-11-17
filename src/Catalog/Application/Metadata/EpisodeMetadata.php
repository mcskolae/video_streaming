<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Metadata;

final readonly class EpisodeMetadata
{
    public function __construct(
        public int $number,
        public string $name,
        public string $overview,
        public ?int $runtimeMinutes,
        public ?string $stillPath,
    ) {}
}
