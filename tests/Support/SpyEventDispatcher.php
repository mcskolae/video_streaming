<?php

declare(strict_types=1);

namespace Tests\Support;

use HomeFlix\Shared\Application\EventDispatcher;
use HomeFlix\Shared\Domain\DomainEvent;

final class SpyEventDispatcher implements EventDispatcher
{
    /** @var list<DomainEvent> */
    public array $dispatched = [];

    public function dispatch(DomainEvent ...$events): void
    {
        array_push($this->dispatched, ...$events);
    }
}
