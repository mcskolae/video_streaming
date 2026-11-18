<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Port;

use HomeFlix\Library\Application\Query\ContinueWatchingItem;
use HomeFlix\Library\Domain\Model\MemberId;

interface ContinueWatchingReader
{
    /**
     * Les titres en cours du membre, le plus récent d'abord : par titre, la dernière vidéo commencée et non terminée.
     * Seuls les titres publiés sont retournés.
     *
     * @return list<ContinueWatchingItem>
     */
    public function for(MemberId $member, int $limit): array;
}
