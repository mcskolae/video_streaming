<?php

declare(strict_types=1);

namespace HomeFlix\Library\Infrastructure\Persistence;

use HomeFlix\Library\Application\Port\MediaDirectory;
use HomeFlix\Library\Domain\Model\MediaRef;
use Illuminate\Support\Facades\DB;

final class DatabaseMediaDirectory implements MediaDirectory
{
    public function playableDuration(MediaRef $media): ?int
    {
        $duration = DB::table('media_assets')
            ->leftJoin('episodes', 'episodes.id', '=', 'media_assets.episode_id')
            ->leftJoin('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->join('titles', 'titles.id', '=', DB::raw('coalesce(media_assets.title_id, seasons.title_id)'))
            ->where('media_assets.id', $media->value)
            ->where('media_assets.status', 'ready')
            ->where('titles.status', 'published')
            ->value('media_assets.duration_seconds');

        return $duration === null ? null : (int) $duration;
    }
}
