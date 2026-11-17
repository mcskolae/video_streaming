<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Command;

use HomeFlix\Media\Application\Port\UploadStore;
use HomeFlix\Shared\Domain\Clock;

/** Supprime les envois abandonnés : sur un petit disque, des centaines de Mo de morceaux orphelins comptent. */
final readonly class PurgeStaleUploadsHandler
{
    public const MAX_AGE_HOURS = 24;

    public function __construct(private UploadStore $store, private Clock $clock) {}

    /** @return int nombre d'envois supprimés */
    public function handle(): int
    {
        return $this->store->purgeInactiveSince($this->clock->now()->modify('-'.self::MAX_AGE_HOURS.' hours'));
    }
}
