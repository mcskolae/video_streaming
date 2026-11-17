<?php

declare(strict_types=1);

use HomeFlix\Media\Application\Upload\UploadMeta;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Infrastructure\Upload\FilesystemUploadStore;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\Filesystem;
use Tests\Support\MediaFixtures as F;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/homeflix-purge-'.bin2hex(random_bytes(4));
    mkdir($this->root, 0755, true);
    config(['media.root' => $this->root]);
    $this->store = new FilesystemUploadStore($this->root, new Filesystem);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

function startedUpload(FilesystemUploadStore $store, int $ageHours): string
{
    $meta = new UploadMeta(F::assetId()->value, PlayableRef::title(F::playableId()), 'mp4', 4, 1);
    $store->begin($meta);
    $directory = config('media.root').'/incoming/_uploads/'.$meta->uploadId;
    touch($directory, time() - $ageHours * 3600);

    return $meta->uploadId;
}

it('removes abandoned uploads and keeps recent ones', function () {
    $old = startedUpload($this->store, 30);
    $recent = startedUpload($this->store, 2);

    $purged = $this->store->purgeInactiveSince(new DateTimeImmutable('-24 hours'));

    expect($purged)->toBe(1)
        ->and($this->store->meta($old))->toBeNull()
        ->and($this->store->meta($recent))->not->toBeNull();
});

it('does nothing when there is no upload or no upload folder', function () {
    expect($this->store->purgeInactiveSince(new DateTimeImmutable('-24 hours')))->toBe(0);
});

it('counts a recent chunk as activity', function () {
    $id = startedUpload($this->store, 30);
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, 'abcd');
    rewind($stream);
    $this->store->storeChunk($id, 0, $stream, 4);

    expect($this->store->purgeInactiveSince(new DateTimeImmutable('-24 hours')))->toBe(0)
        ->and($this->store->meta($id))->not->toBeNull();
});

it('runs through the artisan command', function () {
    startedUpload($this->store, 48);
    startedUpload($this->store, 1);

    $this->artisan('media:purge-uploads')->expectsOutputToContain('1 envoi abandonné supprimé')->assertSuccessful();
});

it('is scheduled every night at 4 o\'clock', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'media:purge-uploads'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 4 * * *');
});
