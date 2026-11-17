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

    /** « title » (film) ou « episode » : lisible par les autres contextes sans importer les types de Media. */
    public function targetKind(): string
    {
        return $this->target->kind->value;
    }

    public function targetId(): string
    {
        return $this->target->id->value;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
