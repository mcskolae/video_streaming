<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Port;

use HomeFlix\Media\Domain\Model\PlayableRef;

/** Question posée au catalogue : ce film ou cet épisode existe-t-il ? */
interface PlayableCatalog
{
    public function exists(PlayableRef $target): bool;

    /** Nom lisible de la cible (« Big Buck Bunny » ou « Sintel Chronicles, S1E2 »), ou null si elle n'existe pas. */
    public function label(PlayableRef $target): ?string;
}
