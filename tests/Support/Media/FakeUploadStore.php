<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use HomeFlix\Media\Application\Exception\InvalidUpload;
use HomeFlix\Media\Application\Port\UploadStore;
use HomeFlix\Media\Application\Upload\UploadMeta;

/** Garde en mémoire la taille des morceaux reçus, sans toucher au disque. */
final class FakeUploadStore implements UploadStore
{
    /** @var array<string, UploadMeta> */
    public array $sessions = [];

    /** @var array<string, array<int, int>> */
    public array $chunks = [];

    /** @var list<string> */
    public array $discarded = [];

    public ?int $assembledSize = null;

    public function begin(UploadMeta $meta): void
    {
        $this->sessions[$meta->uploadId] = $meta;
        $this->chunks[$meta->uploadId] = [];
    }

    public function meta(string $uploadId): ?UploadMeta
    {
        return $this->sessions[$uploadId] ?? null;
    }

    public function storeChunk(string $uploadId, int $index, $stream, int $expectedBytes): int
    {
        $content = stream_get_contents($stream, $expectedBytes + 1);
        $length = strlen((string) $content);

        if ($length !== $expectedBytes) {
            throw InvalidUpload::badChunk();
        }

        $this->chunks[$uploadId][$index] = $length;

        return $length;
    }

    public function receivedChunks(string $uploadId): array
    {
        $indexes = array_keys($this->chunks[$uploadId] ?? []);
        sort($indexes);

        return $indexes;
    }

    public function assemble(string $uploadId, string $destinationAbsolutePath): int
    {
        return $this->assembledSize ?? array_sum($this->chunks[$uploadId] ?? []);
    }

    public function discard(string $uploadId): void
    {
        $this->discarded[] = $uploadId;
        unset($this->sessions[$uploadId], $this->chunks[$uploadId]);
    }

    public ?\DateTimeImmutable $purgedBefore = null;

    public int $purgeResult = 0;

    public function purgeInactiveSince(\DateTimeImmutable $before): int
    {
        $this->purgedBefore = $before;

        return $this->purgeResult;
    }
}
