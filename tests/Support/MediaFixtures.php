<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use HomeFlix\Media\Domain\Model\Duration;
use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableId;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Domain\Model\RelativePath;
use HomeFlix\Media\Domain\Model\Rendition;
use Ramsey\Uuid\Uuid;

final class MediaFixtures
{
    public static function assetId(): MediaAssetId
    {
        return MediaAssetId::fromString(Uuid::uuid7()->toString());
    }

    public static function playableId(): PlayableId
    {
        return PlayableId::fromString(Uuid::uuid7()->toString());
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-17 12:00:00');
    }

    public static function rendition(string $label = '720p', int $width = 1280, int $height = 720): Rendition
    {
        return new Rendition($label, $width, $height, 2500, new RelativePath("hls/abc/{$label}/index.m3u8"));
    }

    /** Un média tout juste reçu pour un film. */
    public static function uploaded(?PlayableRef $target = null): MediaAsset
    {
        return MediaAsset::upload(self::assetId(), $target ?? PlayableRef::title(self::playableId()), new RelativePath('incoming/abc/source.mp4'));
    }

    /** Un média amené dans l'état demandé en suivant les transitions autorisées. */
    public static function inStatus(MediaStatus $status): MediaAsset
    {
        $asset = self::uploaded();

        match ($status) {
            MediaStatus::Uploaded => null,
            MediaStatus::Queued => $asset->queue(),
            MediaStatus::Processing => self::toProcessing($asset),
            MediaStatus::Ready => self::toReady($asset),
            MediaStatus::Failed => $asset->markFailed('ffmpeg a échoué', self::now()),
        };

        $asset->pullDomainEvents();

        return $asset;
    }

    private static function toProcessing(MediaAsset $asset): void
    {
        $asset->queue();
        $asset->startProcessing();
    }

    private static function toReady(MediaAsset $asset): void
    {
        self::toProcessing($asset);
        $asset->markReady(new Duration(600), new RelativePath('hls/abc/master.m3u8'), [self::rendition()], self::now());
    }
}
