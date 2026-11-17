<?php

declare(strict_types=1);

namespace HomeFlix\Shared\Infrastructure;

use HomeFlix\Shared\Application\EventDispatcher;
use HomeFlix\Shared\Domain\DomainEvent;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelEventDispatcher implements EventDispatcher
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function dispatch(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
