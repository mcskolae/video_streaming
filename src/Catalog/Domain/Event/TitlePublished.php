<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Event;

use DateTimeImmutable;
use HomeFlix\Catalog\Domain\Model\TitleId;
use HomeFlix\Shared\Domain\DomainEvent;

final readonly class TitlePublished implements DomainEvent
{
    public function __construct(public TitleId $titleId, private DateTimeImmutable $occurredOn) {}

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
