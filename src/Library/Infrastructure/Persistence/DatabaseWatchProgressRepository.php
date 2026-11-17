<?php

declare(strict_types=1);

namespace HomeFlix\Library\Infrastructure\Persistence;

use DateTimeImmutable;
use HomeFlix\Library\Domain\Model\MediaRef;
use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Model\WatchProgress;
use HomeFlix\Library\Domain\Repository\WatchProgressRepository;
use Illuminate\Support\Facades\DB;

final class DatabaseWatchProgressRepository implements WatchProgressRepository
{
    public function save(WatchProgress $progress): void
    {
        DB::table('watch_progress')->upsert(
            [[
                'user_id' => $progress->member->value,
                'media_asset_id' => $progress->media->value,
                'position_seconds' => $progress->positionSeconds,
                'duration_seconds' => $progress->durationSeconds,
                'completed' => $progress->completed,
                'updated_at' => $progress->updatedAt->format('Y-m-d H:i:s.uP'),
            ]],
            ['user_id', 'media_asset_id'],
            ['position_seconds', 'duration_seconds', 'completed', 'updated_at'],
        );
    }

    public function find(MemberId $member, MediaRef $media): ?WatchProgress
    {
        $row = DB::table('watch_progress')->where('user_id', $member->value)->where('media_asset_id', $media->value)->first();

        return $row === null ? null : new WatchProgress(
            $member,
            $media,
            (int) $row->position_seconds,
            (int) $row->duration_seconds,
            new DateTimeImmutable((string) $row->updated_at),
        );
    }
}
