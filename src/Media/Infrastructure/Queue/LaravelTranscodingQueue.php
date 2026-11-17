<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Queue;

use HomeFlix\Media\Application\Port\TranscodingQueue;
use HomeFlix\Media\Domain\Model\MediaAssetId;

final class LaravelTranscodingQueue implements TranscodingQueue
{
    public function enqueue(MediaAssetId $id): void
    {
        TranscodeMediaJob::dispatch($id->value);
    }
}
