<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Query;

use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Model\TitleRef;
use HomeFlix\Library\Domain\Repository\FavoriteRepository;

final readonly class IsFavoriteHandler
{
    public function __construct(private FavoriteRepository $favorites) {}

    public function handle(string $memberId, string $titleId): bool
    {
        return $this->favorites->exists(MemberId::fromString($memberId), TitleRef::fromString($titleId));
    }
}
