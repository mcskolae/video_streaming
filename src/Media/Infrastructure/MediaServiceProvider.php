<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure;

use HomeFlix\Media\Application\Command\TranscodeMediaHandler;
use HomeFlix\Media\Application\Port\FileTypeDetector;
use HomeFlix\Media\Application\Port\MediaListReader;
use HomeFlix\Media\Application\Port\MediaProbe;
use HomeFlix\Media\Application\Port\MediaStorage;
use HomeFlix\Media\Application\Port\PlayableCatalog;
use HomeFlix\Media\Application\Port\TranscodingQueue;
use HomeFlix\Media\Application\Port\UploadStore;
use HomeFlix\Media\Application\Port\VideoTranscoder;
use HomeFlix\Media\Application\Upload\UploadRules;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use HomeFlix\Media\Infrastructure\Ffmpeg\FfmpegTranscoder;
use HomeFlix\Media\Infrastructure\Ffmpeg\FfprobeMediaProbe;
use HomeFlix\Media\Infrastructure\Persistence\DatabaseMediaListReader;
use HomeFlix\Media\Infrastructure\Persistence\DatabasePlayableCatalog;
use HomeFlix\Media\Infrastructure\Persistence\EloquentMediaAssetRepository;
use HomeFlix\Media\Infrastructure\Queue\LaravelTranscodingQueue;
use HomeFlix\Media\Infrastructure\Storage\LocalMediaStorage;
use HomeFlix\Media\Infrastructure\Upload\FilesystemUploadStore;
use HomeFlix\Media\Infrastructure\Upload\FinfoFileTypeDetector;
use HomeFlix\Shared\Application\EventDispatcher;
use HomeFlix\Shared\Domain\Clock;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MediaAssetRepository::class, EloquentMediaAssetRepository::class);
        $this->app->bind(TranscodingQueue::class, LaravelTranscodingQueue::class);

        $this->app->bind(MediaStorage::class, fn (Application $app) => new LocalMediaStorage(
            (string) config('media.root'),
            $app->make(Filesystem::class),
        ));

        $this->app->bind(PlayableCatalog::class, DatabasePlayableCatalog::class);
        $this->app->bind(MediaListReader::class, DatabaseMediaListReader::class);
        $this->app->bind(FileTypeDetector::class, FinfoFileTypeDetector::class);

        $this->app->bind(UploadStore::class, fn (Application $app) => new FilesystemUploadStore(
            (string) config('media.root'),
            $app->make(Filesystem::class),
        ));

        $this->app->bind(UploadRules::class, fn () => new UploadRules((int) config('media.max_upload_mb') * 1024 * 1024));

        $this->app->bind(MediaProbe::class, fn () => new FfprobeMediaProbe((string) config('media.ffprobe')));

        $this->app->bind(VideoTranscoder::class, fn () => new FfmpegTranscoder(
            (string) config('media.ffmpeg'),
            (int) config('media.transcode_timeout'),
        ));

        $this->app->bind(TranscodeMediaHandler::class, fn (Application $app) => new TranscodeMediaHandler(
            $app->make(MediaAssetRepository::class),
            $app->make(MediaStorage::class),
            $app->make(MediaProbe::class),
            $app->make(VideoTranscoder::class),
            $app->make(EventDispatcher::class),
            $app->make(Clock::class),
            (bool) config('media.delete_source_after_transcoding'),
        ));
    }
}
