<?php

declare(strict_types=1);

use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Media\Application\Command\TranscodeMediaHandler;
use HomeFlix\Media\Application\Port\TranscodingQueue;
use HomeFlix\Media\Domain\Event\MediaAssetFailed;
use HomeFlix\Media\Domain\Event\MediaAssetReady;
use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableId;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Domain\Model\RelativePath;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use HomeFlix\Media\Infrastructure\Queue\TranscodeMediaJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\Support\CatalogFixtures as Catalog;
use Tests\Support\Media\SampleVideos;
use Tests\Support\MediaFixtures as F;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/homeflix-flow-'.bin2hex(random_bytes(4));
    mkdir($this->root.'/incoming/abc', 0755, true);
    config(['media.root' => $this->root, 'media.delete_source_after_transcoding' => true]);

    $this->assets = app(MediaAssetRepository::class);
});

afterEach(function () {
    (new Process(['rm', '-rf', $this->root]))->run();
});

/**
 * Un média en file d'attente, avec un vrai film en base et un fichier source sur le disque.
 */
function queuedMediaWithSource(string $sourceFile): MediaAsset
{
    $n = random_int(1, 1_000_000);
    $movie = Catalog::movie(tmdbId: $n, name: "Film {$n}");
    app(TitleRepository::class)->save($movie);

    $asset = MediaAsset::upload(F::assetId(), PlayableRef::title(PlayableId::fromString($movie->id()->value)), new RelativePath('incoming/abc/source.mp4'));
    $asset->queue();
    app(MediaAssetRepository::class)->save($asset);

    copy($sourceFile, config('media.root').'/incoming/abc/source.mp4');

    return $asset;
}

it('converts a real video from the queue to a ready HLS stream', function () {
    if (! SampleVideos::ffmpegAvailable()) {
        $this->markTestSkipped('ffmpeg et ffprobe ne sont pas installés.');
    }

    $captured = [];
    Event::listen(MediaAssetReady::class, function (MediaAssetReady $event) use (&$captured) {
        $captured[] = $event;
    });
    $asset = queuedMediaWithSource(SampleVideos::video('1920x1080', audio: true, seconds: 3));

    (new TranscodeMediaJob($asset->id()->value))->handle(app(TranscodeMediaHandler::class));

    $loaded = $this->assets->find($asset->id());
    $hls = $this->root.'/hls/'.$asset->id()->value;
    expect($loaded->status())->toBe(MediaStatus::Ready)
        ->and($loaded->duration()?->seconds)->toBe(3)
        ->and($loaded->hlsMasterPath()?->value)->toBe('hls/'.$asset->id()->value.'/master.m3u8')
        ->and(array_map(fn ($r) => $r->label, $loaded->renditions()))->toBe(['720p', '1080p'])
        ->and(DB::table('renditions')->count())->toBe(2)
        ->and(is_file($hls.'/master.m3u8'))->toBeTrue()
        ->and(is_file($hls.'/1080p/index.m3u8'))->toBeTrue()
        ->and(is_file($hls.'/720p/index.m3u8'))->toBeTrue()
        ->and(count(glob($hls.'/*/seg_*.ts') ?: []))->toBeGreaterThanOrEqual(2)
        ->and(file_exists($this->root.'/incoming/abc/source.mp4'))->toBeFalse()
        ->and($captured)->toHaveCount(1)
        ->and($captured[0]->assetId->equals($asset->id()))->toBeTrue();
});

it('keeps the source and cleans the stream when the file is not a video', function () {
    if (! SampleVideos::ffmpegAvailable()) {
        $this->markTestSkipped('ffmpeg et ffprobe ne sont pas installés.');
    }

    $failures = [];
    Event::listen(MediaAssetFailed::class, function (MediaAssetFailed $event) use (&$failures) {
        $failures[] = $event;
    });
    $asset = queuedMediaWithSource(SampleVideos::fake());

    (new TranscodeMediaJob($asset->id()->value))->handle(app(TranscodeMediaHandler::class));

    $loaded = $this->assets->find($asset->id());
    expect($loaded->status())->toBe(MediaStatus::Failed)
        ->and($loaded->errorMessage())->toContain('pas pu être analysé')
        ->and(file_exists($this->root.'/incoming/abc/source.mp4'))->toBeTrue()
        ->and(is_dir($this->root.'/hls/'.$asset->id()->value))->toBeFalse()
        ->and(DB::table('renditions')->count())->toBe(0)
        ->and($failures)->toHaveCount(1);
});

it('can retry a failed media and convert it the second time', function () {
    if (! SampleVideos::ffmpegAvailable()) {
        $this->markTestSkipped('ffmpeg et ffprobe ne sont pas installés.');
    }

    $asset = queuedMediaWithSource(SampleVideos::fake());
    (new TranscodeMediaJob($asset->id()->value))->handle(app(TranscodeMediaHandler::class));

    // L'administrateur remplace le fichier puis relance.
    copy(SampleVideos::video('1280x720', audio: true, seconds: 2), $this->root.'/incoming/abc/source.mp4');
    $failed = $this->assets->find($asset->id());
    $failed->retry();
    $this->assets->save($failed);
    (new TranscodeMediaJob($asset->id()->value))->handle(app(TranscodeMediaHandler::class));

    $loaded = $this->assets->find($asset->id());
    expect($loaded->status())->toBe(MediaStatus::Ready)
        ->and($loaded->errorMessage())->toBeNull()
        ->and(array_map(fn ($r) => $r->label, $loaded->renditions()))->toBe(['720p']);
});

it('runs through the real queue wiring', function () {
    if (! SampleVideos::ffmpegAvailable()) {
        $this->markTestSkipped('ffmpeg et ffprobe ne sont pas installés.');
    }

    config(['queue.default' => 'sync']);
    $asset = queuedMediaWithSource(SampleVideos::video('640x360', audio: false, seconds: 2));

    app(TranscodingQueue::class)->enqueue($asset->id());

    expect($this->assets->find($asset->id())->status())->toBe(MediaStatus::Ready);
});

describe('the queue job', function () {
    it('goes to the "transcoding" queue', function () {
        Queue::fake();
        $id = F::assetId();

        app(TranscodingQueue::class)->enqueue($id);

        Queue::assertPushedOn('transcoding', TranscodeMediaJob::class, fn (TranscodeMediaJob $job) => $job->mediaAssetId === $id->value);
        Queue::assertPushed(TranscodeMediaJob::class, 1);
    });

    it('is tried once, with a long time limit, and never runs twice at the same time', function () {
        $job = new TranscodeMediaJob(F::assetId()->value);
        $middleware = $job->middleware();

        $overlap = $middleware[0] ?? null;

        expect($job->tries)->toBe(1)
            ->and($job->timeout)->toBe(14400)
            ->and($job->failOnTimeout)->toBeTrue()
            ->and($middleware)->toHaveCount(1)
            ->and($overlap)->toBeInstanceOf(WithoutOverlapping::class);
        assert($overlap instanceof WithoutOverlapping);
        expect($overlap->key)->toBe('transcoding');
    });

    it('marks the media as failed when the job itself is interrupted', function () {
        $asset = queuedMediaWithSource(SampleVideos::fake());
        $processing = $this->assets->find($asset->id());
        $processing->startProcessing();
        $this->assets->save($processing);

        (new TranscodeMediaJob($asset->id()->value))->failed(new RuntimeException('Le délai a été dépassé'));

        $loaded = $this->assets->find($asset->id());
        expect($loaded->status())->toBe(MediaStatus::Failed)
            ->and($loaded->errorMessage())->toContain('interrompu')
            ->and($loaded->errorMessage())->toContain('délai');
    });

    it('does nothing for a media that is not waiting', function () {
        $n = random_int(1, 1_000_000);
        $movie = Catalog::movie(tmdbId: $n, name: "Film {$n}");
        app(TitleRepository::class)->save($movie);
        $asset = MediaAsset::upload(F::assetId(), PlayableRef::title(PlayableId::fromString($movie->id()->value)), new RelativePath('incoming/abc/source.mp4'));
        $this->assets->save($asset);

        (new TranscodeMediaJob($asset->id()->value))->handle(app(TranscodeMediaHandler::class));

        expect($this->assets->find($asset->id())->status())->toBe(MediaStatus::Uploaded);
    });
});
