<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Event;

use DateTimeImmutable;
use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Shared\Domain\DomainEvent;

final readonly class MediaAssetFailed implements DomainEvent
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
