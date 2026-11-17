<?php

declare(strict_types=1);

use HomeFlix\Media\Application\Command\RetryMedia;
use HomeFlix\Media\Application\Command\RetryMediaHandler;
use HomeFlix\Media\Application\Exception\MediaNotFound;
use HomeFlix\Media\Application\Exception\SourceMissing;
use HomeFlix\Media\Domain\Exception\InvalidMediaTransition;
use HomeFlix\Media\Domain\Model\MediaStatus;
use Tests\Support\Media\FakeMediaStorage;
use Tests\Support\Media\InMemoryMediaAssetRepository;
use Tests\Support\Media\SpyTranscodingQueue;
use Tests\Support\MediaFixtures as F;

beforeEach(function () {
    $this->assets = new InMemoryMediaAssetRepository;
    $this->storage = new FakeMediaStorage;
    $this->queue = new SpyTranscodingQueue;
    $this->handler = new RetryMediaHandler($this->assets, $this->storage, $this->queue);
});

it('puts a failed media back in the queue, without its error', function () {
    $failed = F::inStatus(MediaStatus::Failed);
    $this->assets->save($failed);

    $this->handler->handle(new RetryMedia($failed->id()->value));

    $asset = $this->assets->find($failed->id());
    expect($asset->status())->toBe(MediaStatus::Queued)
        ->and($asset->errorMessage())->toBeNull()
        ->and($this->queue->enqueued)->toBe([$failed->id()->value]);
});

it('refuses anything that is not failed and enqueues nothing', function (MediaStatus $status) {
    $asset = F::inStatus($status);
    $this->assets->save($asset);

    expect(fn () => $this->handler->handle(new RetryMedia($asset->id()->value)))->toThrow(InvalidMediaTransition::class);

    expect($this->queue->enqueued)->toBe([])->and($this->assets->find($asset->id())->status())->toBe($status);
})->with([MediaStatus::Uploaded, MediaStatus::Queued, MediaStatus::Processing, MediaStatus::Ready]);

it('does not requeue a media whose source file is gone, and keeps it failed with its message', function () {
    $failed = F::inStatus(MediaStatus::Failed);
    $this->assets->save($failed);
    $this->storage->missing = [$failed->sourcePath()->value];

    expect(fn () => $this->handler->handle(new RetryMedia($failed->id()->value)))->toThrow(SourceMissing::class, 'renvoyez la vidéo');

    $asset = $this->assets->find($failed->id());
    expect($asset->status())->toBe(MediaStatus::Failed)->and($asset->errorMessage())->not->toBeNull()->and($this->queue->enqueued)->toBe([]);
});

it('fails for an unknown media', function () {
    $this->handler->handle(new RetryMedia(F::assetId()->value));
})->throws(MediaNotFound::class);

it('refuses an identifier that is not a UUID', function () {
    $this->handler->handle(new RetryMedia('../../x'));
})->throws(InvalidArgumentException::class);

it('can be retried again after failing again', function () {
    $failed = F::inStatus(MediaStatus::Failed);
    $this->assets->save($failed);

    $this->handler->handle(new RetryMedia($failed->id()->value));
    $again = $this->assets->find($failed->id());
    $again->startProcessing();
    $again->markFailed('encore', F::now());
    $this->assets->save($again);
    $this->handler->handle(new RetryMedia($failed->id()->value));

    expect($this->queue->enqueued)->toHaveCount(2);
});
