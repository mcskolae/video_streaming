<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Command;

use HomeFlix\Media\Application\Exception\InvalidUpload;
use HomeFlix\Media\Application\Port\PlayableCatalog;
use HomeFlix\Media\Application\Port\UploadStore;
use HomeFlix\Media\Application\Upload\UploadMeta;
use HomeFlix\Media\Application\Upload\UploadRules;
use HomeFlix\Media\Application\Upload\UploadSession;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableId;
use HomeFlix\Media\Domain\Model\PlayableKind;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use HomeFlix\Shared\Application\IdGenerator;
use InvalidArgumentException;

final readonly class StartUploadHandler
{
    public function __construct(
        private UploadStore $store,
        private PlayableCatalog $catalog,
        private MediaAssetRepository $assets,
        private IdGenerator $ids,
        private UploadRules $rules,
    ) {}

    /**
     * @throws InvalidUpload
     * @throws InvalidArgumentException si l'identifiant de la cible n'est pas un UUID
     */
    public function handle(StartUpload $command): UploadSession
    {
        $extension = UploadRules::extensionOf($command->filename) ?? throw InvalidUpload::unsupportedFormat();

        if ($command->sizeBytes < 1) {
            throw InvalidUpload::empty();
        }

        if ($command->sizeBytes > $this->rules->maxSizeBytes) {
            throw InvalidUpload::tooLarge((int) ($this->rules->maxSizeBytes / 1024 / 1024));
        }

        if ($command->totalChunks !== $this->rules->chunksFor($command->sizeBytes)) {
            throw InvalidUpload::inconsistentChunks();
        }

        $targetId = PlayableId::fromString($command->targetId);
        $target = $command->kind === PlayableKind::Title ? PlayableRef::title($targetId) : PlayableRef::episode($targetId);

        if (! $this->catalog->exists($target)) {
            throw InvalidUpload::unknownTarget();
        }

        // Une seule vidéo par film ou épisode. Seule une vidéo en échec peut être remplacée.
        $existing = $this->assets->findByTarget($target);

        if ($existing !== null && $existing->status() !== MediaStatus::Failed) {
            throw InvalidUpload::mediaAlreadyExists();
        }

        $uploadId = $this->ids->generate();
        $this->store->begin(new UploadMeta($uploadId, $target, $extension, $command->sizeBytes, $command->totalChunks));

        return new UploadSession($uploadId, UploadRules::CHUNK_SIZE_BYTES, $command->totalChunks);
    }
}
