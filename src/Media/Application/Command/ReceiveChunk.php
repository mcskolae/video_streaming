<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Command;

final readonly class ReceiveChunk
{
    /** @param  resource  $stream */
    public function __construct(
        public string $uploadId,
        public int $index,
        public mixed $stream,
    ) {}
}
