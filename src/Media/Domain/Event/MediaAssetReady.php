<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Event;

use DateTimeImmutable;
use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Shared\Domain\DomainEvent;

/** Le flux HLS est prêt : le contexte Catalog peut marquer le film ou l'épisode comme lisible. */
final readonly class MediaAssetReady implements DomainEvent
{
    public function __construct(
        public MediaAssetId $assetId,
        public PlayableRef $target,
        private DateTimeImmutable $occurredOn,
    ) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
