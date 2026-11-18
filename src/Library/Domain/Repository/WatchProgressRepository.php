<?php

declare(strict_types=1);

namespace HomeFlix\Library\Domain\Repository;

use HomeFlix\Library\Domain\Model\MediaRef;
use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Model\WatchProgress;

interface WatchProgressRepository
{
    /** Crée ou remplace la progression du membre pour cette vidéo. */
    public function save(WatchProgress $progress): void;

    public function find(MemberId $member, MediaRef $media): ?WatchProgress;
}
