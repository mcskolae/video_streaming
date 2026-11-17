<?php

declare(strict_types=1);

use HomeFlix\Shared\Domain\AggregateRoot;
use HomeFlix\Shared\Domain\DomainEvent;

final class SomethingHappened implements DomainEvent
{
    public function occurredOn(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-16 12:00:00');
    }
}

final class FakeAggregate extends AggregateRoot
{
    public function doSomething(): void
    {
        $this->record(new SomethingHappened);
    }
}

it('records events in order and empties the list once pulled', function () {
    $aggregate = new FakeAggregate;
    $aggregate->doSomething();
    $aggregate->doSomething();

    expect($aggregate->pullDomainEvents())->toHaveCount(2)
        ->and($aggregate->pullDomainEvents())->toBeEmpty();
});
