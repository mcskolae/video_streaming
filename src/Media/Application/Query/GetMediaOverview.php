<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Query;

use HomeFlix\Media\Domain\Model\PlayableKind;

final readonly class GetMediaOverview
{
    public function __construct(public PlayableKind $kind, public string $targetId) {}
}
