<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Command;

use HomeFlix\Media\Domain\Model\PlayableKind;

final readonly class StartUpload
{
    public function __construct(
        public PlayableKind $kind,
        public string $targetId,
        public string $filename,
        public int $sizeBytes,
        public int $totalChunks,
    ) {}
}
