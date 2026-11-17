<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Port;

use HomeFlix\Media\Application\Exception\TranscodingFailed;
use HomeFlix\Media\Application\Transcoding\ProbeResult;

interface MediaProbe
{
    /**
     * Analyse un fichier : il doit contenir une vidéo de durée et de taille exploitables.
     *
     * @throws TranscodingFailed si ce n'est pas une vidéo lisible
     */
    public function probe(string $absolutePath): ProbeResult;
}
