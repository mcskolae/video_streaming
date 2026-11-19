<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Query;

use HomeFlix\Library\Application\Port\TitleProgressReader;
use HomeFlix\Library\Domain\Model\MemberId;

final readonly class ListTitleProgressHandler
{
    public function __construct(private TitleProgressReader $reader) {}

    /** @return array<string, EpisodeProgress> par identifiant de vidéo */
    public function handle(string $memberId, string $slug): array
    {
        return $this->reader->forTitle(MemberId::fromString($memberId), $slug);
    }
}
