<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Query;

use HomeFlix\Library\Application\Port\FavoritesReader;
use HomeFlix\Library\Domain\Model\MemberId;

final readonly class ListFavoritesHandler
{
    public function __construct(private FavoritesReader $reader) {}

    /** @return list<FavoriteCard> */
    public function handle(string $memberId): array
    {
        return $this->reader->publishedFor(MemberId::fromString($memberId));
    }
}
