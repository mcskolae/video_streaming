<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Command;

use HomeFlix\Catalog\Application\Exception\TitleNotFound;
use HomeFlix\Catalog\Domain\Exception\TitleCannotBePublished;
use HomeFlix\Catalog\Domain\Model\TitleId;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Shared\Application\EventDispatcher;
use HomeFlix\Shared\Domain\Clock;
use InvalidArgumentException;

final readonly class PublishTitleHandler
{
    public function __construct(
        private TitleRepository $titles,
        private Clock $clock,
        private EventDispatcher $events,
    ) {}

    /**
     * @throws InvalidArgumentException si l'identifiant n'est pas un UUID
     * @throws TitleNotFound
     * @throws TitleCannotBePublished tant qu'aucun média n'est prêt
     */
    public function handle(PublishTitle $command): void
    {
        $id = TitleId::fromString($command->titleId);
        $title = $this->titles->find($id) ?? throw TitleNotFound::withId($id);

        $title->publish($this->clock->now());

        $this->titles->save($title);
        $this->events->dispatch(...$title->pullDomainEvents());
    }
}
