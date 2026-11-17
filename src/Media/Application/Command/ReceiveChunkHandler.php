<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Command;

use HomeFlix\Media\Application\Exception\InvalidUpload;
use HomeFlix\Media\Application\Port\UploadStore;
use HomeFlix\Media\Application\Upload\UploadRules;

final readonly class ReceiveChunkHandler
{
    public function __construct(private UploadStore $store) {}

    /**
     * Enregistre un morceau. Tous les morceaux font 5 Mo sauf le dernier, qui fait exactement le reste.
     *
     * @return int taille du morceau reçu
     *
     * @throws InvalidUpload
     */
    public function handle(ReceiveChunk $command): int
    {
        $meta = $this->store->meta($command->uploadId) ?? throw InvalidUpload::unknownUpload();

        if ($command->index < 0 || $command->index >= $meta->totalChunks) {
            throw InvalidUpload::badChunk();
        }

        $isLast = $command->index === $meta->totalChunks - 1;
        $expected = $isLast ? $meta->sizeBytes - UploadRules::CHUNK_SIZE_BYTES * ($meta->totalChunks - 1) : UploadRules::CHUNK_SIZE_BYTES;

        // Le stockage refuse tout morceau qui n'a pas exactement la taille attendue et n'en garde rien.
        $received = $this->store->storeChunk($command->uploadId, $command->index, $command->stream, $expected);

        return $received;
    }
}
