<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Port;

use HomeFlix\Media\Application\Query\AdminMediaRow;

/** Port de lecture : la liste des vidéos se lit directement, sans reconstruire les agrégats. */
interface MediaListReader
{
    /**
     * Les vidéos les plus récemment modifiées d'abord.
     *
     * @return list<AdminMediaRow>
     */
    public function recent(int $limit): array;
}
