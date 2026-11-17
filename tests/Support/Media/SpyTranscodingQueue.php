<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use HomeFlix\Media\Application\Port\TranscodingQueue;
use HomeFlix\Media\Domain\Model\MediaAssetId;

final class SpyTranscodingQueue implements TranscodingQueue
{
    /** @var list<string> */
    public array $enqueued = [];

    public function enqueue(MediaAssetId $id): void
    {
        $this->enqueued[] = $id->value;
    }
}
