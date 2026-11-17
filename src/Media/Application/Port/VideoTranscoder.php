<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Port;

use HomeFlix\Media\Application\Exception\TranscodingFailed;
use HomeFlix\Media\Application\Transcoding\RenditionPlan;
use HomeFlix\Media\Application\Transcoding\TranscodedRendition;

interface VideoTranscoder
{
    /**
     * Convertit une vidéo en flux HLS dans le dossier de sortie : un `master.m3u8` et, par qualité,
     * un dossier `<libellé>/` avec sa playlist et ses segments.
     *
     * @param  non-empty-list<RenditionPlan>  $plans
     * @return non-empty-list<TranscodedRendition>
     *
     * @throws TranscodingFailed
     */
    public function transcode(string $sourceAbsolutePath, string $outputDirectory, array $plans, bool $hasAudio): array;
}
