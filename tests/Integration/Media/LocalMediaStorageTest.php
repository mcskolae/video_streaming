<?php

declare(strict_types=1);

use HomeFlix\Media\Domain\Model\RelativePath;
use HomeFlix\Media\Infrastructure\Storage\LocalMediaStorage;
use Illuminate\Filesystem\Filesystem;
use Tests\Support\MediaFixtures as F;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/homeflix-storage-'.bin2hex(random_bytes(4));
    mkdir($this->root, 0755, true);
    $this->storage = new LocalMediaStorage($this->root, new Filesystem);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

it('turns a relative path into an absolute one under the root', function () {
    expect($this->storage->absolutePath(new RelativePath('incoming/abc/source.mp4')))->toBe($this->root.'/incoming/abc/source.mp4');
});

it('ignores a trailing slash in the root', function () {
    $storage = new LocalMediaStorage($this->root.'/', new Filesystem);

    expect($storage->absolutePath(new RelativePath('a/b.mp4')))->toBe($this->root.'/a/b.mp4');
});

it('prepares an empty HLS folder named after the media', function () {
    $id = F::assetId();

    $directory = $this->storage->prepareHlsDirectory($id);

    expect($directory)->toBe($this->root.'/hls/'.$id->value)
        ->and(is_dir($directory))->toBeTrue()
        ->and($this->storage->hlsRelativePath($id))->toBe('hls/'.$id->value);
});

it('empties the folder of a previous attempt', function () {
    $id = F::assetId();
    $directory = $this->storage->prepareHlsDirectory($id);
    mkdir($directory.'/720p');
    file_put_contents($directory.'/720p/seg_00000.ts', 'ancien');

    $this->storage->prepareHlsDirectory($id);

    expect(glob($directory.'/*'))->toBe([]);
});

it('deletes a file, and does not complain when it is already gone', function () {
    mkdir($this->root.'/incoming/abc', 0755, true);
    file_put_contents($this->root.'/incoming/abc/source.mp4', 'x');
    $path = new RelativePath('incoming/abc/source.mp4');

    $this->storage->deleteFile($path);
    $this->storage->deleteFile($path);

    expect(file_exists($this->root.'/incoming/abc/source.mp4'))->toBeFalse();
});

it('deletes the HLS folder of one media and leaves the others alone', function () {
    $mine = F::assetId();
    $other = F::assetId();
    $mineDir = $this->storage->prepareHlsDirectory($mine);
    $otherDir = $this->storage->prepareHlsDirectory($other);
    file_put_contents($mineDir.'/master.m3u8', 'x');
    file_put_contents($otherDir.'/master.m3u8', 'y');

    $this->storage->deleteHlsDirectory($mine);

    expect(is_dir($mineDir))->toBeFalse()->and(is_file($otherDir.'/master.m3u8'))->toBeTrue();
});

it('does not fail when deleting the folder of a media that never had one', function () {
    $this->storage->deleteHlsDirectory(F::assetId());

    expect(is_dir($this->root.'/hls'))->toBeFalse();
});
