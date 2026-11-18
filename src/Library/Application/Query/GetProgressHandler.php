<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Query;

use HomeFlix\Library\Domain\Model\MediaRef;
use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Repository\WatchProgressRepository;

final readonly class GetProgressHandler
{
    public function __construct(private WatchProgressRepository $progress) {}

    /** La position où reprendre, ou 0 si rien n'est enregistré ou si la vidéo a été vue en entier. */
    public function handle(string $memberId, string $mediaId): int
    {
        $progress = $this->progress->find(MemberId::fromString($memberId), MediaRef::fromString($mediaId));

        return $progress === null || $progress->completed ? 0 : $progress->positionSeconds;
    }
}
