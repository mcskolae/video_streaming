<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Query;

use HomeFlix\Library\Application\Port\ContinueWatchingReader;
use HomeFlix\Library\Domain\Model\MemberId;

/** « Reprendre où vous en êtes » sur la page d'un titre : la reprise ou l'épisode suivant. */
final readonly class GetTitleResumeHandler
{
    public function __construct(private ContinueWatchingReader $reader) {}

    public function handle(string $memberId, string $slug): ?ContinueWatchingItem
    {
        return $this->reader->forTitle(MemberId::fromString($memberId), $slug);
    }
}
