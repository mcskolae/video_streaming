<?php

declare(strict_types=1);

use HomeFlix\Media\Application\Command\CompleteUpload;
use HomeFlix\Media\Application\Command\CompleteUploadHandler;
use HomeFlix\Media\Application\Command\ReceiveChunk;
use HomeFlix\Media\Application\Command\ReceiveChunkHandler;
use HomeFlix\Media\Application\Command\StartUpload;
use HomeFlix\Media\Application\Command\StartUploadHandler;
use HomeFlix\Media\Application\Exception\InvalidUpload;
use HomeFlix\Media\Application\Exception\TranscodingFailed;
use HomeFlix\Media\Application\Upload\UploadRules;
use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableKind;
use HomeFlix\Media\Domain\Model\PlayableRef;
use Tests\Support\Media\FakeFileTypeDetector;
use Tests\Support\Media\FakeMediaProbe;
use Tests\Support\Media\FakeMediaStorage;
use Tests\Support\Media\FakePlayableCatalog;
use Tests\Support\Media\FakeUploadStore;
use Tests\Support\Media\InMemoryMediaAssetRepository;
use Tests\Support\Media\SpyTranscodingQueue;
use Tests\Support\MediaFixtures as F;
use Tests\Support\UuidV7Generator;

const MB = 1024 * 1024;
const CHUNK = 5 * MB;

beforeEach(function () {
    $this->store = new FakeUploadStore;
    $this->catalog = new FakePlayableCatalog;
    $this->assets = new InMemoryMediaAssetRepository;
    $this->storage = new FakeMediaStorage;
    $this->types = new FakeFileTypeDetector;
    $this->probe = new FakeMediaProbe;
    $this->queue = new SpyTranscodingQueue;
    $this->rules = new UploadRules(100 * MB);

    $this->movieId = F::playableId();
    $this->target = PlayableRef::title($this->movieId);
    $this->catalog->known = [$this->target];

    $this->start = new StartUploadHandler($this->store, $this->catalog, $this->assets, new UuidV7Generator, $this->rules);
    $this->receive = new ReceiveChunkHandler($this->store);
    $this->complete = new CompleteUploadHandler($this->store, $this->storage, $this->types, $this->probe, $this->assets, $this->queue, new UuidV7Generator, $this->rules);

    $this->begin = fn (int $size = 12 * MB, string $name = 'film.mp4', ?PlayableKind $kind = null, ?string $target = null) => $this->start->handle(
        new StartUpload($kind ?? PlayableKind::Title, $target ?? $this->movieId->value, $name, $size, (int) ceil($size / CHUNK)),
    );

    $this->send = function (string $uploadId, int $index, int $bytes) {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, str_repeat('x', $bytes));
        rewind($stream);

        return $this->receive->handle(new ReceiveChunk($uploadId, $index, $stream));
    };

    /** Envoie tous les morceaux d'un fichier de la taille donnée. */
    $this->sendAll = function (string $uploadId, int $size) {
        $total = (int) ceil($size / CHUNK);
        foreach (range(0, $total - 1) as $i) {
            ($this->send)($uploadId, $i, $i === $total - 1 ? $size - CHUNK * ($total - 1) : CHUNK);
        }
    };
});

describe('starting', function () {
    it('opens an upload and tells the browser how to cut the file', function () {
        $session = ($this->begin)(12 * MB);

        expect($session->chunkSizeBytes)->toBe(CHUNK)
            ->and($session->totalChunks)->toBe(3)
            ->and($this->store->meta($session->uploadId)?->extension)->toBe('mp4')
            ->and($this->store->meta($session->uploadId)?->target->equals($this->target))->toBeTrue();
    });

    it('accepts the usual video formats and keeps only a known extension', function (string $name, string $extension) {
        $session = ($this->begin)(MB, $name);

        expect($this->store->meta($session->uploadId)?->extension)->toBe($extension);
    })->with([['a.mp4', 'mp4'], ['A.MKV', 'mkv'], ['a.mov', 'mov'], ['a.webm', 'webm'], ['a.avi', 'avi'], ['a.m4v', 'm4v'], ['a.tar.mp4', 'mp4']]);

    it('refuses other formats and tricky names', function (string $name) {
        ($this->begin)(MB, $name);
    })->with([['a.exe'], ['a.php'], ['a.mp4.php'], ['a'], ['.mp4x'], ['a.html'], ['a.m3u8'], ['../../a.sh'], ['a.mp4 '], ['']])->throws(InvalidUpload::class, 'Format non accepté');

    it('refuses an empty file and a file that is too big', function (int $size) {
        ($this->begin)($size);
    })->with([[0], [-5], [100 * MB + 1], [PHP_INT_MAX]])->throws(InvalidUpload::class);

    it('refuses a chunk count that does not match the size', function (int $declared) {
        $this->start->handle(new StartUpload(PlayableKind::Title, $this->movieId->value, 'a.mp4', 12 * MB, $declared));
    })->with([[1], [2], [4], [0], [-1]])->throws(InvalidUpload::class, 'découpage');

    it('refuses an unknown movie or episode', function () {
        ($this->begin)(MB, 'a.mp4', PlayableKind::Title, F::playableId()->value);
    })->throws(InvalidUpload::class, 'n\'existe pas');

    it('does not mistake an episode for a movie', function () {
        ($this->begin)(MB, 'a.mp4', PlayableKind::Episode, $this->movieId->value);
    })->throws(InvalidUpload::class, 'n\'existe pas');

    it('accepts an episode that exists', function () {
        $episode = PlayableRef::episode(F::playableId());
        $this->catalog->known[] = $episode;

        $session = ($this->begin)(MB, 'a.mp4', PlayableKind::Episode, $episode->id->value);

        expect($this->store->meta($session->uploadId)?->target->kind)->toBe(PlayableKind::Episode);
    });

    it('refuses an identifier that is not a UUID', function () {
        ($this->begin)(MB, 'a.mp4', PlayableKind::Title, '../../etc');
    })->throws(InvalidArgumentException::class);

    it('refuses a second video for a title, except to replace a failed one', function (MediaStatus $status, bool $allowed) {
        $asset = F::inStatus($status);
        $this->assets->save(MediaAsset::reconstitute(
            $asset->id(), $this->target, $asset->sourcePath(), $status, $asset->duration(), $asset->errorMessage(), $asset->hlsMasterPath(), $asset->renditions(),
        ));

        if ($allowed) {
            expect(($this->begin)(MB)->uploadId)->not->toBeEmpty();

            return;
        }

        expect(fn () => ($this->begin)(MB))->toThrow(InvalidUpload::class, 'existe déjà');
    })->with([
        'uploaded' => [MediaStatus::Uploaded, false],
        'queued' => [MediaStatus::Queued, false],
        'processing' => [MediaStatus::Processing, false],
        'ready' => [MediaStatus::Ready, false],
        'failed' => [MediaStatus::Failed, true],
    ]);
});

describe('receiving chunks', function () {
    it('stores each chunk and reports its size', function () {
        $session = ($this->begin)(12 * MB);

        expect(($this->send)($session->uploadId, 0, CHUNK))->toBe(CHUNK)
            ->and(($this->send)($session->uploadId, 2, 2 * MB))->toBe(2 * MB)
            ->and($this->store->receivedChunks($session->uploadId))->toBe([0, 2]);
    });

    it('accepts a chunk again without damage', function () {
        $session = ($this->begin)(12 * MB);

        ($this->send)($session->uploadId, 1, CHUNK);
        ($this->send)($session->uploadId, 1, CHUNK);

        expect($this->store->receivedChunks($session->uploadId))->toBe([1]);
    });

    it('refuses a chunk number outside the file', function (int $index) {
        $session = ($this->begin)(12 * MB);

        ($this->send)($session->uploadId, $index, CHUNK);
    })->with([[-1], [3], [99999]])->throws(InvalidUpload::class, 'Morceau invalide');

    it('refuses a chunk of the wrong size, in the middle and at the end', function (int $index, int $bytes) {
        $session = ($this->begin)(12 * MB);

        ($this->send)($session->uploadId, $index, $bytes);
    })->with([
        'middle too small' => [0, CHUNK - 1],
        'middle too big' => [1, CHUNK + 1],
        'last too small' => [2, 2 * MB - 1],
        'last too big' => [2, 2 * MB + 1],
        'empty' => [0, 0],
    ])->throws(InvalidUpload::class, 'Morceau invalide');

    it('accepts a last chunk that is exactly the remainder, and a file that is an exact multiple', function () {
        $exact = ($this->begin)(10 * MB);

        expect(($this->send)($exact->uploadId, 1, CHUNK))->toBe(CHUNK);
    });

    it('refuses a chunk for an unknown or expired upload', function () {
        ($this->send)(F::assetId()->value, 0, 10);
    })->throws(InvalidUpload::class, 'Envoi inconnu');
});

describe('completing', function () {
    it('assembles a valid video, creates the media and puts it in the transcoding queue', function () {
        $session = ($this->begin)(12 * MB);
        ($this->sendAll)($session->uploadId, 12 * MB);

        $assetId = $this->complete->handle(new CompleteUpload($session->uploadId));

        $asset = $this->assets->find($assetId);
        expect($asset->status())->toBe(MediaStatus::Queued)
            ->and($asset->target()->equals($this->target))->toBeTrue()
            ->and($asset->sourcePath()->value)->toBe('incoming/'.$assetId->value.'/source.mp4')
            ->and($this->queue->enqueued)->toBe([$assetId->value])
            ->and($this->store->discarded)->toBe([$session->uploadId]);
    });

    it('never puts the name sent by the browser on the disk', function () {
        $session = ($this->begin)(MB, 'Ma vidéo; rm -rf.MKV');
        ($this->sendAll)($session->uploadId, MB);
        $this->types->mime = 'video/x-matroska';

        $assetId = $this->complete->handle(new CompleteUpload($session->uploadId));

        expect($this->assets->find($assetId)->sourcePath()->value)->toBe('incoming/'.$assetId->value.'/source.mkv');
    });

    it('refuses to assemble while chunks are missing', function () {
        $session = ($this->begin)(12 * MB);
        ($this->send)($session->uploadId, 0, CHUNK);
        ($this->send)($session->uploadId, 2, 2 * MB);

        expect(fn () => $this->complete->handle(new CompleteUpload($session->uploadId)))->toThrow(InvalidUpload::class, 'Tous les morceaux');
        expect($this->queue->enqueued)->toBe([])->and($this->store->meta($session->uploadId))->not->toBeNull();
    });

    it('refuses a file whose real content is not a video, whatever its name says', function (string $mime) {
        $session = ($this->begin)(MB, 'a.mp4');
        ($this->sendAll)($session->uploadId, MB);
        $this->types->mime = $mime;

        try {
            $this->complete->handle(new CompleteUpload($session->uploadId));
            $this->fail('An exception was expected.');
        } catch (InvalidUpload $exception) {
            expect($exception->getMessage())->toContain('pas une vidéo')
                ->and($this->queue->enqueued)->toBe([])
                ->and($this->store->discarded)->toBe([$session->uploadId])
                ->and(count($this->storage->deletedFiles))->toBe(1);
        }
    })->with([['text/html'], ['application/x-php'], ['application/x-dosexec'], ['text/plain'], ['image/png'], ['application/octet-stream'], ['video/x-msvideo']]);

    it('refuses a file that looks like a video but cannot be read as one', function () {
        $session = ($this->begin)(MB);
        ($this->sendAll)($session->uploadId, MB);
        $this->probe->failure = TranscodingFailed::notAVideo();

        expect(fn () => $this->complete->handle(new CompleteUpload($session->uploadId)))->toThrow(InvalidUpload::class, 'pas une vidéo');
        expect($this->queue->enqueued)->toBe([]);
    });

    it('refuses an assembled file whose size differs from the announced size', function () {
        $session = ($this->begin)(12 * MB);
        ($this->sendAll)($session->uploadId, 12 * MB);
        $this->store->assembledSize = 12 * MB - 1;

        expect(fn () => $this->complete->handle(new CompleteUpload($session->uploadId)))->toThrow(InvalidUpload::class, 'taille reçue');
        expect($this->queue->enqueued)->toBe([]);
    });

    it('replaces a failed media', function () {
        $failed = F::inStatus(MediaStatus::Failed);
        $this->assets->save(MediaAsset::reconstitute(
            $failed->id(), $this->target, $failed->sourcePath(), MediaStatus::Failed, null, 'échec', null, [],
        ));
        $session = ($this->begin)(MB);
        ($this->sendAll)($session->uploadId, MB);

        $assetId = $this->complete->handle(new CompleteUpload($session->uploadId));

        expect($this->assets->find($failed->id()))->toBeNull()
            ->and($this->assets->findByTarget($this->target)?->id()->equals($assetId))->toBeTrue()
            ->and($this->storage->deletedFiles)->toContain($failed->sourcePath()->value)
            ->and($this->storage->deletedDirectories)->toBe([$failed->id()->value]);
    });

    it('does not replace a media that appeared since the upload started', function () {
        $session = ($this->begin)(MB);
        ($this->sendAll)($session->uploadId, MB);
        $other = F::inStatus(MediaStatus::Queued);
        $this->assets->save(MediaAsset::reconstitute(
            $other->id(), $this->target, $other->sourcePath(), MediaStatus::Queued, null, null, null, [],
        ));

        expect(fn () => $this->complete->handle(new CompleteUpload($session->uploadId)))->toThrow(InvalidUpload::class, 'existe déjà');
        expect($this->queue->enqueued)->toBe([])->and($this->assets->find($other->id())?->status())->toBe(MediaStatus::Queued);
    });

    it('fails for an unknown upload and cannot be completed twice', function () {
        $session = ($this->begin)(MB);
        ($this->sendAll)($session->uploadId, MB);
        $this->complete->handle(new CompleteUpload($session->uploadId));

        expect(fn () => $this->complete->handle(new CompleteUpload($session->uploadId)))->toThrow(InvalidUpload::class, 'Envoi inconnu');
        expect(fn () => $this->complete->handle(new CompleteUpload(F::assetId()->value)))->toThrow(InvalidUpload::class, 'Envoi inconnu');
        expect($this->queue->enqueued)->toHaveCount(1);
    });
});
