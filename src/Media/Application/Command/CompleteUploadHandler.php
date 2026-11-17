<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Command;

use HomeFlix\Media\Application\Exception\InvalidUpload;
use HomeFlix\Media\Application\Exception\TranscodingFailed;
use HomeFlix\Media\Application\Port\FileTypeDetector;
use HomeFlix\Media\Application\Port\MediaProbe;
use HomeFlix\Media\Application\Port\MediaStorage;
use HomeFlix\Media\Application\Port\TranscodingQueue;
use HomeFlix\Media\Application\Port\UploadStore;
use HomeFlix\Media\Application\Upload\UploadRules;
use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\RelativePath;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use HomeFlix\Shared\Application\IdGenerator;

final readonly class CompleteUploadHandler
{
    public function __construct(
        private UploadStore $store,
        private MediaStorage $storage,
        private FileTypeDetector $types,
        private MediaProbe $probe,
        private MediaAssetRepository $assets,
        private TranscodingQueue $queue,
        private IdGenerator $ids,
        private UploadRules $rules,
    ) {}

    /**
     * Assemble les morceaux, vérifie que c'est bien une vidéo, crée le média et le met en file de transcodage.
     * Le fichier est contrôlé sur son contenu réel : son nom et le type annoncé par le navigateur ne comptent pas.
     *
     * @throws InvalidUpload
     */
    public function handle(CompleteUpload $command): MediaAssetId
    {
        $meta = $this->store->meta($command->uploadId) ?? throw InvalidUpload::unknownUpload();

        if (count($this->store->receivedChunks($command->uploadId)) !== $meta->totalChunks) {
            throw InvalidUpload::incomplete();
        }

        // Le nom du fichier sur le disque est généré : rien de ce que le navigateur a envoyé n'y figure, sauf une extension de la liste autorisée.
        $assetId = MediaAssetId::fromString($this->ids->generate());
        $source = new RelativePath('incoming/'.$assetId->value.'/source.'.$meta->extension);
        $absolute = $this->storage->absolutePath($source);

        try {
            $size = $this->store->assemble($command->uploadId, $absolute);

            if ($size !== $meta->sizeBytes) {
                throw InvalidUpload::sizeMismatch();
            }

            if (! $this->rules->acceptsMime($meta->extension, $this->types->mimeType($absolute))) {
                throw InvalidUpload::notAVideo();
            }

            try {
                $this->probe->probe($absolute);
            } catch (TranscodingFailed) {
                throw InvalidUpload::notAVideo();
            }
        } catch (InvalidUpload $exception) {
            $this->storage->deleteFile($source);
            $this->store->discard($command->uploadId);

            throw $exception;
        }

        // Une vidéo en échec est remplacée : son ancien média disparaît pour laisser la place au nouveau.
        $existing = $this->assets->findByTarget($meta->target);

        if ($existing !== null) {
            if ($existing->status() !== MediaStatus::Failed) {
                $this->storage->deleteFile($source);
                $this->store->discard($command->uploadId);

                throw InvalidUpload::mediaAlreadyExists();
            }

            $this->assets->delete($existing->id());
            $this->storage->deleteFile($existing->sourcePath());
            $this->storage->deleteHlsDirectory($existing->id());
        }

        $asset = MediaAsset::upload($assetId, $meta->target, $source);
        $asset->queue();
        $this->assets->save($asset);
        $this->store->discard($command->uploadId);
        $this->queue->enqueue($asset->id());

        return $asset->id();
    }
}
