<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Transcoding;

/** Ce que l'analyse d'un fichier a trouvé. */
final readonly class ProbeResult
{
    public function __construct(
        public int $durationSeconds,
        public int $width,
        public int $height,
        public bool $hasAudio,
    ) {}
}
