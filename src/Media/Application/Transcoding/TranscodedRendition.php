<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Transcoding;

/** Une qualité produite, avec sa playlist relative au dossier de sortie. */
final readonly class TranscodedRendition
{
    public function __construct(
        public string $label,
        public int $width,
        public int $height,
        public int $videoBitrateKbps,
        /** Chemin relatif au dossier de sortie, par exemple "720p/index.m3u8". */
        public string $playlist,
    ) {}
}
