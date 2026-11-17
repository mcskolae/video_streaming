<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use HomeFlix\Media\Application\Port\FileTypeDetector;

final class FakeFileTypeDetector implements FileTypeDetector
{
    public function __construct(public string $mime = 'video/mp4') {}

    public function mimeType(string $absolutePath): string
    {
        return $this->mime;
    }
}
