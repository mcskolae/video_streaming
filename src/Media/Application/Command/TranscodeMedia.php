<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Command;

final readonly class TranscodeMedia
{
    public function __construct(public string $mediaAssetId) {}
}
