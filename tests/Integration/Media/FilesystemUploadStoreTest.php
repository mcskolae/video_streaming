<?php

declare(strict_types=1);

use HomeFlix\Media\Application\Exception\InvalidUpload;
use HomeFlix\Media\Application\Upload\UploadMeta;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Infrastructure\Upload\FilesystemUploadStore;
use Illuminate\Filesystem\Filesystem;
use Tests\Support\MediaFixtures as F;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/homeflix-upload-'.bin2hex(random_bytes(4));
    mkdir($this->root, 0755, true);
    $this->store = new FilesystemUploadStore($this->root, new Filesystem);
    $this->meta = new UploadMeta(F::assetId()->value, PlayableRef::title(F::playableId()), 'mp4', 12, 3);
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->root);
});

/** @return resource */
function streamOf(string $content)
{
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $content);
    rewind($stream);

    return $stream;
}

it('remembers an upload and its target', function () {
    $this->store->begin($this->meta);

    $loaded = $this->store->meta($this->meta->uploadId);

    expect($loaded)->not->toBeNull()
        ->and($loaded->extension)->toBe('mp4')
        ->and($loaded->sizeBytes)->toBe(12)
        ->and($loaded->totalChunks)->toBe(3)
        ->and($loaded->target->equals($this->meta->target))->toBeTrue();
});

it('does not know an upload that was never started', function () {
    expect($this->store->meta(F::assetId()->value))->toBeNull()->and($this->store->receivedChunks(F::assetId()->value))->toBe([]);
});

it('never turns an identifier that is not a UUID into a path', function (string $identifier) {
    expect($this->store->meta($identifier))->toBeNull()
        ->and($this->store->receivedChunks($identifier))->toBe([]);

    $this->store->discard($identifier);

    expect(is_dir($this->root))->toBeTrue();
})->with([['../../etc'], ['..'], ['/etc/passwd'], ['a/b'], [''], ['0190a6e4-7c3b-7c1e-9a52-3f1d5c8e2b10/../..'], ["x\0y"]]);

it('refuses to store a chunk for an invalid identifier', function () {
    $this->store->storeChunk('../../evil', 0, streamOf('abc'), 3);
})->throws(InvalidArgumentException::class);

it('stores chunks, lists them in order and writes them atomically', function () {
    $this->store->begin($this->meta);

    $this->store->storeChunk($this->meta->uploadId, 2, streamOf('cccc'), 4);
    $this->store->storeChunk($this->meta->uploadId, 0, streamOf('aaaa'), 4);

    expect($this->store->receivedChunks($this->meta->uploadId))->toBe([0, 2]);
    expect(glob($this->root.'/incoming/_uploads/'.$this->meta->uploadId.'/*.tmp'))->toBe([]);
});

it('replaces a chunk that is sent again', function () {
    $this->store->begin($this->meta);
    $this->store->storeChunk($this->meta->uploadId, 0, streamOf('ancien'), 6);
    $this->store->storeChunk($this->meta->uploadId, 0, streamOf('nouveau'), 7);

    expect(file_get_contents($this->root.'/incoming/_uploads/'.$this->meta->uploadId.'/chunk_0'))->toBe('nouveau')
        ->and($this->store->receivedChunks($this->meta->uploadId))->toBe([0]);
});

it('refuses a chunk that is not exactly the expected size, and keeps nothing of it', function (string $content) {
    $this->store->begin($this->meta);

    expect(fn () => $this->store->storeChunk($this->meta->uploadId, 0, streamOf($content), 10))
        ->toThrow(InvalidUpload::class);

    expect($this->store->receivedChunks($this->meta->uploadId))->toBe([])
        ->and(glob($this->root.'/incoming/_uploads/'.$this->meta->uploadId.'/chunk_*'))->toBe([])
        ->and(glob($this->root.'/incoming/_uploads/'.$this->meta->uploadId.'/*.tmp'))->toBe([]);
})->with(['too big' => [str_repeat('x', 1000)], 'one byte too many' => [str_repeat('x', 11)], 'one byte short' => [str_repeat('x', 9)], 'empty' => ['']]);

it('keeps a previous valid chunk when a bad replacement is refused', function () {
    $this->store->begin($this->meta);
    $this->store->storeChunk($this->meta->uploadId, 0, streamOf('AAAA'), 4);

    expect(fn () => $this->store->storeChunk($this->meta->uploadId, 0, streamOf('x'), 4))->toThrow(InvalidUpload::class);

    expect(file_get_contents($this->root.'/incoming/_uploads/'.$this->meta->uploadId.'/chunk_0'))->toBe('AAAA')
        ->and($this->store->receivedChunks($this->meta->uploadId))->toBe([0]);
});

it('assembles the chunks in order, whatever the order they arrived in', function () {
    $this->store->begin($this->meta);
    $this->store->storeChunk($this->meta->uploadId, 2, streamOf('CCCC'), 4);
    $this->store->storeChunk($this->meta->uploadId, 0, streamOf('AAAA'), 4);
    $this->store->storeChunk($this->meta->uploadId, 1, streamOf('BBBB'), 4);

    $size = $this->store->assemble($this->meta->uploadId, $this->root.'/incoming/x/source.mp4');

    expect($size)->toBe(12)->and(file_get_contents($this->root.'/incoming/x/source.mp4'))->toBe('AAAABBBBCCCC');
});

it('does not assemble when a chunk is missing', function () {
    $this->store->begin($this->meta);
    $this->store->storeChunk($this->meta->uploadId, 0, streamOf('AAAA'), 4);
    $this->store->storeChunk($this->meta->uploadId, 2, streamOf('CCCC'), 4);

    expect(fn () => $this->store->assemble($this->meta->uploadId, $this->root.'/incoming/x/source.mp4'))->toThrow(InvalidUpload::class);
});

it('does not assemble an unknown upload', function () {
    expect(fn () => $this->store->assemble(F::assetId()->value, $this->root.'/x.mp4'))->toThrow(InvalidUpload::class);
});

it('removes everything about an upload when it is discarded, and nothing else', function () {
    $other = new UploadMeta(F::assetId()->value, PlayableRef::title(F::playableId()), 'mp4', 4, 1);
    $this->store->begin($this->meta);
    $this->store->begin($other);
    $this->store->storeChunk($this->meta->uploadId, 0, streamOf('AAAA'), 4);

    $this->store->discard($this->meta->uploadId);

    expect($this->store->meta($this->meta->uploadId))->toBeNull()
        ->and($this->store->receivedChunks($this->meta->uploadId))->toBe([])
        ->and($this->store->meta($other->uploadId))->not->toBeNull();
});

it('treats a damaged description as an unknown upload', function (string $content) {
    $this->store->begin($this->meta);
    file_put_contents($this->root.'/incoming/_uploads/'.$this->meta->uploadId.'/meta.json', $content);

    expect($this->store->meta($this->meta->uploadId))->toBeNull();
})->with([
    'not json' => ['not json'],
    'empty list' => ['[]'],
    'unknown kind' => ['{"kind":"x"}'],
    'missing fields' => ['{"kind":"title","target":"0190a6e4-7c3b-7c1e-9a52-3f1d5c8e2b10"}'],
    'invalid target' => ['{"kind":"title","target":"not-a-uuid","extension":"mp4","size":1,"chunks":1}'],
    'wrong types' => ['{"kind":"title","target":"0190a6e4-7c3b-7c1e-9a52-3f1d5c8e2b10","extension":"mp4","size":"1","chunks":"1"}'],
    'null' => ['null'],
]);
