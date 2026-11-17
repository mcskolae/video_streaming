<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Port;

use HomeFlix\Library\Domain\Model\TitleRef;

/** Question posée au catalogue : ce titre est-il publié ? */
interface TitleDirectory
{
    public function isPublished(TitleRef $title): bool;
}
