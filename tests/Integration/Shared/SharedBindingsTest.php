<?php

declare(strict_types=1);

use HomeFlix\Shared\Application\EventDispatcher;
use HomeFlix\Shared\Application\IdGenerator;
use HomeFlix\Shared\Domain\Clock;
use HomeFlix\Shared\Domain\DomainEvent;
use HomeFlix\Shared\Domain\Uuid;
use Illuminate\Support\Facades\Event;

final readonly class StubbedId extends Uuid {}

it('binds the shared ports to their adapters', function () {
    expect(app(Clock::class)->now())->toBeInstanceOf(DateTimeImmutable::class)
        ->and(StubbedId::fromString(app(IdGenerator::class)->generate()))->toBeInstanceOf(StubbedId::class);
});

it('publishes domain events through the Laravel dispatcher', function () {
    $received = [];
    Event::listen(DomainEvent::class, function (DomainEvent $event) use (&$received) {
        $received[] = $event;
    });

    $event = new class implements DomainEvent
    {
        public function occurredOn(): DateTimeImmutable
        {
            return new DateTimeImmutable;
        }
    };

    app(EventDispatcher::class)->dispatch($event);

    expect($received)->toBe([$event]);
});
