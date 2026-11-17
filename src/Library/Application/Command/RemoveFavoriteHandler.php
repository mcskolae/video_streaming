<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Command;

use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Model\TitleRef;
use HomeFlix\Library\Domain\Repository\FavoriteRepository;
use InvalidArgumentException;

final readonly class RemoveFavoriteHandler
{
    public function __construct(private FavoriteRepository $favorites) {}

    /**
     * Retire un favori, même si le titre n'est plus publié (sinon un favori masqué ne pourrait jamais être retiré).
     * Sans effet si ce n'était pas un favori.
     *
     * @throws InvalidArgumentException si un identifiant n'est pas un UUID
     */
    public function handle(RemoveFavorite $command): void
    {
        $this->favorites->remove(MemberId::fromString($command->memberId), TitleRef::fromString($command->titleId));
    }
}
