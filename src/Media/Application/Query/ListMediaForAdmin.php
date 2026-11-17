<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Query;

use HomeFlix\Media\Application\Port\MediaListReader;

final readonly class ListMediaForAdmin
{
    public const LIMIT = 100;

    public function __construct(private MediaListReader $reader) {}

    /** @return list<AdminMediaRow> */
    public function handle(): array
    {
        return $this->reader->recent(self::LIMIT);
    }
}
