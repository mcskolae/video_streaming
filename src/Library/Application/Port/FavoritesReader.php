<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Port;

use HomeFlix\Library\Application\Query\FavoriteCard;
use HomeFlix\Library\Domain\Model\MemberId;

interface FavoritesReader
{
    /**
     * Les favoris d'un membre, le plus récent d'abord, **limités aux titres publiés**.
     * Un titre dépublié reste en favori mais n'apparaît plus ici.
     *
     * @return list<FavoriteCard>
     */
    public function publishedFor(MemberId $member): array;
}
