<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query\Public;

final readonly class PublicEpisode
{
    public function __construct(
        public int $number,
        public string $name,
        public string $overview,
        public ?int $runtimeMinutes,
        public ?string $stillPath,
        public bool $playable,
        /** Chemin public du flux HLS de l'épisode s'il est prêt, sinon null. */
        public ?string $streamPath = null,
    ) {}
}
