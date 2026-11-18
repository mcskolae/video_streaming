<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Query;

use HomeFlix\Library\Application\Port\ContinueWatchingReader;
use HomeFlix\Library\Domain\Model\MemberId;

final readonly class ListContinueWatchingHandler
{
    public const LIMIT = 12;

    public function __construct(private ContinueWatchingReader $reader) {}

    /** @return list<ContinueWatchingItem> */
    public function handle(string $memberId): array
    {
        return $this->reader->for(MemberId::fromString($memberId), self::LIMIT);
    }
}
