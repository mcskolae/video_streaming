<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use HomeFlix\Media\Application\Port\PlayableCatalog;
use HomeFlix\Media\Domain\Model\PlayableRef;

final class FakePlayableCatalog implements PlayableCatalog
{
    /** @var list<PlayableRef> */
    public array $known = [];

    public function exists(PlayableRef $target): bool
    {
        foreach ($this->known as $ref) {
            if ($ref->equals($target)) {
                return true;
            }
        }

        return false;
    }

    public function label(PlayableRef $target): ?string
    {
        return $this->exists($target) ? 'Titre de test' : null;
    }
}
