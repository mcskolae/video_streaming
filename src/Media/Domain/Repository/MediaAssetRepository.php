<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Repository;

use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Model\PlayableRef;

interface MediaAssetRepository
{
    /** Crée ou met à jour le média avec ses qualités. */
    public function save(MediaAsset $asset): void;

    public function find(MediaAssetId $id): ?MediaAsset;

    /** Le média d'un film ou d'un épisode. Il n'y en a qu'un par cible. */
    public function findByTarget(PlayableRef $target): ?MediaAsset;

    /** Supprime un média et ses qualités. */
    public function delete(MediaAssetId $id): void;
}
