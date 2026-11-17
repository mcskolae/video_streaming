<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Upload;

use HomeFlix\Media\Application\Port\FileTypeDetector;

/** Détecte le type réel d'un fichier d'après son contenu (extension `fileinfo`). */
final class FinfoFileTypeDetector implements FileTypeDetector
{
    public function mimeType(string $absolutePath): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath);

        return $mime === false ? 'application/octet-stream' : $mime;
    }
}
