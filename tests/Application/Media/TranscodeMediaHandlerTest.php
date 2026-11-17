<?php

declare(strict_types=1);

use HomeFlix\Media\Application\Command\TranscodeMedia;
use HomeFlix\Media\Application\Command\TranscodeMediaHandler;
use HomeFlix\Media\Application\Exception\TranscodingFailed;
use HomeFlix\Media\Application\Transcoding\ProbeResult;
use HomeFlix\Media\Domain\Event\MediaAssetFailed;
use HomeFlix\Media\Domain\Event\MediaAssetReady;
use HomeFlix\Media\Domain\Model\MediaStatus;
use Tests\Support\FixedClock;
use Tests\Support\Media\FakeMediaProbe;
use Tests\Support\Media\FakeMediaStorage;
use Tests\Support\Media\FakeVideoTranscoder;
use Tests\Support\Media\InMemoryMediaAssetRepository;
use Tests\Support\MediaFixtures as F;
use Tests\Support\SpyEventDispatcher;

beforeEach(function () {
    $this->assets = new InMemoryMediaAssetRepository;
    $this->storage = new FakeMediaStorage;
    $this->probe = new FakeMediaProbe;
    $this->transcoder = new FakeVideoTranscoder;
    $this->events = new SpyEventDispatcher;
    $this->make = fn (bool $deleteSource = true) => new TranscodeMediaHandler(
        $this->assets, $this->storage, $this->probe, $this->transcoder, $this->events, new FixedClock, $deleteSource,
    );
    $this->handler = ($this->make)();

    $this->queued = F::inStatus(MediaStatus::Queued);
    $this->assets->save($this->queued);
});

it('converts a queued media and makes it ready', function () {
    $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

    $asset = $this->assets->find($this->queued->id());
    expect($asset->status())->toBe(MediaStatus::Ready)
        ->and($asset->duration()?->seconds)->toBe(600)
        ->and($asset->hlsMasterPath()?->value)->toBe('hls/'.$this->queued->id()->value.'/master.m3u8')
        ->and(array_map(fn ($r) => [$r->label, $r->playlistPath->value], $asset->renditions()))->toBe([
            ['1080p', 'hls/'.$this->queued->id()->value.'/1080p/index.m3u8'],
            ['720p', 'hls/'.$this->queued->id()->value.'/720p/index.m3u8'],
        ]);
});

it('announces that the media is ready', function () {
    $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

    $event = $this->events->dispatched[0] ?? null;
    expect($this->events->dispatched)->toHaveCount(1)->and($event)->toBeInstanceOf(MediaAssetReady::class);
    assert($event instanceof MediaAssetReady);
    expect($event->assetId->equals($this->queued->id()))->toBeTrue()
        ->and($event->target->equals($this->queued->target()))->toBeTrue();
});

it('saves the media as processing before the conversion starts', function () {
    $seen = null;
    $this->transcoder->during = function () use (&$seen) {
        $seen = $this->assets->find($this->queued->id())->status();
    };

    $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

    expect($seen)->toBe(MediaStatus::Processing)
        ->and($this->assets->statusHistory)->toBe(['queued', 'processing', 'ready']);
});

it('asks for the qualities that fit the source and tells whether there is sound', function (ProbeResult $result, array $labels, bool $audio) {
    $this->probe->result = $result;

    $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

    expect(array_map(fn ($p) => $p->label, $this->transcoder->receivedPlans))->toBe($labels)
        ->and($this->transcoder->receivedHasAudio)->toBe($audio);
})->with([
    'full HD with sound' => [new ProbeResult(600, 1920, 1080, true), ['1080p', '720p'], true],
    'HD without sound' => [new ProbeResult(60, 1280, 720, false), ['720p'], false],
    'small source' => [new ProbeResult(60, 640, 360, true), ['360p'], true],
]);

describe('source file', function () {
    it('is deleted once the stream is ready', function () {
        $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

        expect($this->storage->deletedFiles)->toBe(['incoming/abc/source.mp4']);
    });

    it('is kept when deletion is turned off', function () {
        ($this->make)(false)->handle(new TranscodeMedia($this->queued->id()->value));

        expect($this->storage->deletedFiles)->toBe([]);
    });

    it('is kept after a failure, so that a retry can start again', function () {
        $this->transcoder->failure = TranscodingFailed::ffmpeg('codec non supporté');

        $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

        expect($this->storage->deletedFiles)->toBe([]);
    });
});

describe('failures', function () {
    it('marks the media as failed with a readable message when the file is not a video', function () {
        $this->probe->failure = TranscodingFailed::notAVideo();

        $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

        $asset = $this->assets->find($this->queued->id());
        expect($asset->status())->toBe(MediaStatus::Failed)
            ->and($asset->errorMessage())->toBe('Le fichier n\'est pas une vidéo lisible.')
            ->and($this->transcoder->calls)->toBe(0);
    });

    it('marks the media as failed when ffmpeg fails, announces it and cleans the half-written stream', function () {
        $this->transcoder->failure = TranscodingFailed::ffmpeg('Error while opening encoder');

        $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

        $asset = $this->assets->find($this->queued->id());
        expect($asset->status())->toBe(MediaStatus::Failed)
            ->and($asset->errorMessage())->toContain('Error while opening encoder')
            ->and($this->storage->deletedDirectories)->toBe([$this->queued->id()->value])
            ->and($this->events->dispatched)->toHaveCount(1)
            ->and($this->events->dispatched[0])->toBeInstanceOf(MediaAssetFailed::class);
    });

    it('does not throw, so the queue goes on with the next media', function () {
        $this->transcoder->failure = TranscodingFailed::ffmpeg('boom');

        expect(fn () => $this->handler->handle(new TranscodeMedia($this->queued->id()->value)))->not->toThrow(Throwable::class);
    });

    it('treats an invalid output of the converter as a conversion failure', function () {
        $this->transcoder->forcedLabels = ['HD', '720p'];

        $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

        expect($this->assets->find($this->queued->id())->status())->toBe(MediaStatus::Failed)
            ->and($this->events->dispatched[0])->toBeInstanceOf(MediaAssetFailed::class);
    });

    it('fails on a size it cannot plan for', function () {
        $this->probe->result = new ProbeResult(60, 1, 1, false);

        $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

        expect($this->assets->find($this->queued->id())->status())->toBe(MediaStatus::Failed);
    });

    it('can be retried after a failure and then succeeds', function () {
        $this->transcoder->failure = TranscodingFailed::ffmpeg('première fois');
        $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

        $failed = $this->assets->find($this->queued->id());
        $failed->retry();
        $this->assets->save($failed);
        $this->transcoder->failure = null;
        $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

        $asset = $this->assets->find($this->queued->id());
        expect($asset->status())->toBe(MediaStatus::Ready)->and($asset->errorMessage())->toBeNull();
    });
});

describe('a job that arrives at the wrong time', function () {
    it('does nothing unless the media is waiting in the queue', function (MediaStatus $status) {
        $asset = F::inStatus($status);
        $this->assets->save($asset);
        $before = $this->assets->statusHistory;

        $this->handler->handle(new TranscodeMedia($asset->id()->value));

        expect($this->probe->calls)->toBe(0)
            ->and($this->transcoder->calls)->toBe(0)
            ->and($this->assets->statusHistory)->toBe($before)
            ->and($this->events->dispatched)->toBe([]);
    })->with([MediaStatus::Uploaded, MediaStatus::Processing, MediaStatus::Ready, MediaStatus::Failed]);

    it('does nothing for an unknown media', function () {
        $this->handler->handle(new TranscodeMedia(F::assetId()->value));

        expect($this->probe->calls)->toBe(0);
    });

    it('refuses an identifier that is not a UUID', function () {
        $this->handler->handle(new TranscodeMedia('not-a-uuid'));
    })->throws(InvalidArgumentException::class);

    it('does not convert the same media twice', function () {
        $this->handler->handle(new TranscodeMedia($this->queued->id()->value));
        $this->handler->handle(new TranscodeMedia($this->queued->id()->value));

        expect($this->transcoder->calls)->toBe(1)->and($this->events->dispatched)->toHaveCount(1);
    });
});

describe('interrupted job', function () {
    it('marks a media that was being processed as failed', function () {
        $processing = F::inStatus(MediaStatus::Processing);
        $this->assets->save($processing);

        $this->handler->fail($processing->id()->value, 'Le transcodage a été interrompu : délai dépassé');

        $asset = $this->assets->find($processing->id());
        expect($asset->status())->toBe(MediaStatus::Failed)
            ->and($asset->errorMessage())->toContain('interrompu')
            ->and($this->storage->deletedDirectories)->toBe([$processing->id()->value])
            ->and($this->events->dispatched[0])->toBeInstanceOf(MediaAssetFailed::class);
    });

    it('never touches a media that is already ready or unknown', function () {
        $ready = F::inStatus(MediaStatus::Ready);
        $this->assets->save($ready);

        $this->handler->fail($ready->id()->value, 'x');
        $this->handler->fail(F::assetId()->value, 'x');

        expect($this->assets->find($ready->id())->status())->toBe(MediaStatus::Ready)
            ->and($this->events->dispatched)->toBe([])
            ->and($this->storage->deletedDirectories)->toBe([]);
    });
});
