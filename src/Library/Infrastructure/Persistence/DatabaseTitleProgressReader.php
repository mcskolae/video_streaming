<?php

declare(strict_types=1);

namespace HomeFlix\Library\Infrastructure\Persistence;

use HomeFlix\Library\Application\Port\TitleProgressReader;
use HomeFlix\Library\Application\Query\EpisodeProgress;
use HomeFlix\Library\Domain\Model\MemberId;
use Illuminate\Support\Facades\DB;

final class DatabaseTitleProgressReader implements TitleProgressReader
{
    public function forTitle(MemberId $member, string $slug): array
    {
        $rows = DB::select(
            <<<'SQL'
            select p.media_asset_id, p.position_seconds, p.duration_seconds, p.completed
            from watch_progress p
            join media_assets m on m.id = p.media_asset_id and m.status = 'ready'
            left join episodes e on e.id = m.episode_id
            left join seasons s on s.id = e.season_id
            join titles t on t.id = coalesce(m.title_id, s.title_id) and t.status = 'published'
            where p.user_id = ? and t.slug = ? and p.position_seconds > 0
            SQL,
            [$member->value, $slug],
        );

        $progress = [];

        foreach ($rows as $row) {
            $progress[(string) $row->media_asset_id] = new EpisodeProgress((int) $row->position_seconds, (int) $row->duration_seconds, (bool) $row->completed);
        }

        return $progress;
    }
}
