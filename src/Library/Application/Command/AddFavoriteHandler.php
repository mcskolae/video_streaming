<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Command;

use HomeFlix\Library\Application\Exception\TitleNotAvailable;
use HomeFlix\Library\Application\Port\TitleDirectory;
use HomeFlix\Library\Domain\Model\Favorite;
use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Model\TitleRef;
use HomeFlix\Library\Domain\Repository\FavoriteRepository;
use HomeFlix\Shared\Domain\Clock;
use InvalidArgumentException;

final readonly class AddFavoriteHandler
{
    public function __construct(
        private FavoriteRepository $favorites,
        private TitleDirectory $titles,
        private Clock $clock,
    ) {}

    /**
     * Ajoute un titre publié aux favoris. L'ajout est idempotent : refaire la même demande ne change rien.
     *
     * @throws InvalidArgumentException si un identifiant n'est pas un UUID
     * @throws TitleNotAvailable si le titre n'existe pas ou n'est pas publié
     */
    public function handle(AddFavorite $command): void
    {
        $member = MemberId::fromString($command->memberId);
        $title = TitleRef::fromString($command->titleId);

        if (! $this->titles->isPublished($title)) {
            throw TitleNotAvailable::create();
        }

        $this->favorites->add(new Favorite($member, $title, $this->clock->now()));
    }
}
