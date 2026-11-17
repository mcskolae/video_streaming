<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Port;

interface FileTypeDetector
{
    /** Type MIME réel d'après le contenu du fichier, jamais d'après son nom. */
    public function mimeType(string $absolutePath): string;
}
