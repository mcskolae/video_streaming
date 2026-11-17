<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Transcoding;

/** Une qualité à produire. */
final readonly class RenditionPlan
{
    public function __construct(
        public string $label,
        public int $width,
        public int $height,
        public int $videoBitrateKbps,
    ) {}
}
