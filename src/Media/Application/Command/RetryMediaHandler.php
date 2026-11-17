<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Command;

use HomeFlix\Media\Application\Exception\MediaNotFound;
use HomeFlix\Media\Application\Exception\SourceMissing;
use HomeFlix\Media\Application\Port\MediaStorage;
use HomeFlix\Media\Application\Port\TranscodingQueue;
use HomeFlix\Media\Domain\Exception\InvalidMediaTransition;
use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use InvalidArgumentException;

final readonly class RetryMediaHandler
{
    public function __construct(
        private MediaAssetRepository $assets,
        private MediaStorage $storage,
        private TranscodingQueue $queue,
    ) {}

    /**
     * Relance un média en échec : il repart en file de transcodage, sans son message d'erreur.
     *
     * @throws InvalidArgumentException si l'identifiant n'est pas un UUID
     * @throws MediaNotFound
     * @throws InvalidMediaTransition si le média n'est pas en échec
     * @throws SourceMissing si le fichier source a disparu
     */
    public function handle(RetryMedia $command): void
    {
        $asset = $this->assets->find(MediaAssetId::fromString($command->mediaAssetId)) ?? throw MediaNotFound::create();

        // On ne remet pas en file une vidéo dont le fichier n'existe plus : elle échouerait aussitôt, sans explication utile.
        if (! $this->storage->exists($asset->sourcePath())) {
            throw SourceMissing::create();
        }

        $asset->retry();
        $this->assets->save($asset);
        $this->queue->enqueue($asset->id());
    }
}
