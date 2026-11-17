<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Upload;

use HomeFlix\Media\Domain\Model\PlayableRef;

/** Ce que l'on sait d'un envoi en cours. */
final readonly class UploadMeta
{
    public function __construct(
        public string $uploadId,
        public PlayableRef $target,
        /** Extension déduite de la liste autorisée, jamais prise telle quelle du nom du fichier. */
        public string $extension,
        public int $sizeBytes,
        public int $totalChunks,
    ) {}
}
