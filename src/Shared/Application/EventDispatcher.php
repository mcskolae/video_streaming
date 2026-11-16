<?php

declare(strict_types=1);

namespace HomeFlix\Shared\Application;

use HomeFlix\Shared\Domain\DomainEvent;

interface EventDispatcher
{
    public function dispatch(DomainEvent ...$events): void;
}
