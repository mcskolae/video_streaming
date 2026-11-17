<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Port;

use HomeFlix\Media\Domain\Model\MediaAssetId;

/** File d'attente des transcodages : un seul à la fois, car le serveur n'a pas d'encodage matériel. */
interface TranscodingQueue
{
    public function enqueue(MediaAssetId $id): void;
}
