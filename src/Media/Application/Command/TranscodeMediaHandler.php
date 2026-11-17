<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Command;

use HomeFlix\Media\Application\Exception\TranscodingFailed;
use HomeFlix\Media\Application\Port\MediaProbe;
use HomeFlix\Media\Application\Port\MediaStorage;
use HomeFlix\Media\Application\Port\VideoTranscoder;
use HomeFlix\Media\Application\Transcoding\RenditionPlanner;
use HomeFlix\Media\Application\Transcoding\TranscodedRendition;
use HomeFlix\Media\Domain\Model\Duration;
use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\RelativePath;
use HomeFlix\Media\Domain\Model\Rendition;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use HomeFlix\Shared\Application\EventDispatcher;
use HomeFlix\Shared\Domain\Clock;
use InvalidArgumentException;
use Throwable;

/**
 * Convertit un média en file d'attente en flux HLS.
 *
 * Un échec de conversion ne lève jamais d'exception : le média passe en `failed` avec un message lisible,
 * la file continue, l'administrateur peut relancer.
 */
final readonly class TranscodeMediaHandler
{
    public function __construct(
        private MediaAssetRepository $assets,
        private MediaStorage $storage,
        private MediaProbe $probe,
        private VideoTranscoder $transcoder,
        private EventDispatcher $events,
        private Clock $clock,
        private bool $deleteSourceAfterSuccess = true,
    ) {}

    public function handle(TranscodeMedia $command): void
    {
        $asset = $this->assets->find(MediaAssetId::fromString($command->mediaAssetId));

        // Une tâche en double, ou arrivée après coup, ne doit rien casser : seul un média en file est traité.
        if ($asset === null || $asset->status() !== MediaStatus::Queued) {
            return;
        }

        $asset->startProcessing();
        $this->assets->save($asset);

        try {
            $this->convert($asset);
        } catch (TranscodingFailed|InvalidArgumentException $exception) {
            $this->fail($asset, $exception->getMessage());
        }
    }

    /** Appelée quand la tâche elle-même échoue (délai dépassé, mémoire) : le média ne doit pas rester « en cours ». */
    public function fail(MediaAsset|string $asset, string $message): void
    {
        if (is_string($asset)) {
            $asset = $this->assets->find(MediaAssetId::fromString($asset));
        }

        if ($asset === null || ! in_array($asset->status(), [MediaStatus::Uploaded, MediaStatus::Queued, MediaStatus::Processing], true)) {
            return;
        }

        $this->storage->deleteHlsDirectory($asset->id());
        $asset->markFailed($message, $this->clock->now());
        $this->assets->save($asset);
        $this->events->dispatch(...$asset->pullDomainEvents());
    }

    private function convert(MediaAsset $asset): void
    {
        $probe = $this->probe->probe($this->storage->absolutePath($asset->sourcePath()));
        $plans = RenditionPlanner::plan($probe->width, $probe->height);

        $outputDirectory = $this->storage->prepareHlsDirectory($asset->id());
        $produced = $this->transcoder->transcode(
            $this->storage->absolutePath($asset->sourcePath()),
            $outputDirectory,
            $plans,
            $probe->hasAudio,
        );

        $base = $this->storage->hlsRelativePath($asset->id());

        try {
            $renditions = array_map(fn (TranscodedRendition $r) => new Rendition(
                $r->label, $r->width, $r->height, $r->videoBitrateKbps, new RelativePath($base.'/'.$r->playlist),
            ), $produced);

            $asset->markReady(new Duration($probe->durationSeconds), new RelativePath($base.'/master.m3u8'), $renditions, $this->clock->now());
        } catch (Throwable $exception) {
            // Une qualité invalide produite par le convertisseur est un échec de conversion, pas une erreur de programme.
            throw TranscodingFailed::ffmpeg($exception->getMessage());
        }

        $this->assets->save($asset);
        $this->events->dispatch(...$asset->pullDomainEvents());

        if ($this->deleteSourceAfterSuccess) {
            $this->storage->deleteFile($asset->sourcePath());
        }
    }
}
