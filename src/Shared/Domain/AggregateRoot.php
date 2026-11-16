<?php

declare(strict_types=1);

namespace HomeFlix\Shared\Domain;

abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    final protected function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Retourne les événements enregistrés puis vide la liste.
     * Le handler de l'application les publie une fois l'agrégat sauvegardé.
     *
     * @return list<DomainEvent>
     */
    final public function pullDomainEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
