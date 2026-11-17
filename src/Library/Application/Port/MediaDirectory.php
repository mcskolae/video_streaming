<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Port;

use HomeFlix\Library\Domain\Model\MediaRef;

/** Port sortant : ce que Library a besoin de savoir d'une vidéo. */
interface MediaDirectory
{
    /** Durée en secondes d'une vidéo prête et publiée, ou null si elle n'existe pas ou n'est pas lisible. */
    public function playableDuration(MediaRef $media): ?int;
}
