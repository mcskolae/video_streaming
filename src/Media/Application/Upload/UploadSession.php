<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Upload;

/** Ce que le navigateur reçoit au démarrage d'un envoi. */
final readonly class UploadSession
{
    public function __construct(
        public string $uploadId,
        public int $chunkSizeBytes,
        public int $totalChunks,
    ) {}
}
