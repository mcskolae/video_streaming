<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Port;

use HomeFlix\Library\Application\Query\EpisodeProgress;
use HomeFlix\Library\Domain\Model\MemberId;

interface TitleProgressReader
{
    /**
     * La progression du membre pour chaque vidéo commencée d'un titre publié, indexée par identifiant de vidéo.
     * Une position à 0 n'est pas une progression.
     *
     * @return array<string, EpisodeProgress>
     */
    public function forTitle(MemberId $member, string $slug): array;
}
