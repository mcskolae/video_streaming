<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Model;

use InvalidArgumentException;

/** Une qualité d'un flux HLS (par exemple 720p), avec sa playlist. */
final readonly class Rendition
{
    public function __construct(
        public string $label,
        public int $width,
        public int $height,
        public int $videoBitrateKbps,
        public RelativePath $playlistPath,
    ) {
        if (preg_match('/^[0-9]{3,4}p$/', $label) !== 1) {
            throw new InvalidArgumentException(sprintf('A rendition label looks like "720p", got "%s".', $label));
        }

        if ($width < 1 || $width > 8192 || $height < 1 || $height > 8192) {
            throw new InvalidArgumentException('A rendition size is 1 to 8192 pixels on each side.');
        }

        if ($videoBitrateKbps < 1 || $videoBitrateKbps > 100000) {
            throw new InvalidArgumentException('A video bitrate is 1 to 100000 kbit/s.');
        }
    }
}
