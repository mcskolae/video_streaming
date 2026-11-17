<?php

declare(strict_types=1);

namespace HomeFlix\Library\Infrastructure\Persistence;

use HomeFlix\Library\Application\Port\ContinueWatchingReader;
use HomeFlix\Library\Application\Query\ContinueWatchingItem;
use HomeFlix\Library\Application\Query\FavoriteKind;
use HomeFlix\Library\Domain\Model\MemberId;
use Illuminate\Support\Facades\DB;

final class DatabaseContinueWatchingReader implements ContinueWatchingReader
{
    public function for(MemberId $member, int $limit): array
    {
        // Par titre, la progression non terminée la plus récente (DISTINCT ON), puis les titres les plus récents d'abord.
        // Requête fixe : les valeurs viennent de paramètres liés.
        $rows = DB::select(
            <<<'SQL'
            select * from (
                select distinct on (t.id)
                    t.slug, t.type, t.title, t.release_year, t.poster_path,
                    p.media_asset_id, p.position_seconds, p.duration_seconds, p.updated_at,
                    case when e.id is null then null else s.number || 'x' || e.number end as episode_label
                from watch_progress p
                join media_assets m on m.id = p.media_asset_id and m.status = 'ready'
                left join episodes e on e.id = m.episode_id
                left join seasons s on s.id = e.season_id
                join titles t on t.id = coalesce(m.title_id, s.title_id) and t.status = 'published'
                where p.user_id = ? and not p.completed and p.position_seconds > 0
                order by t.id, p.updated_at desc
            ) latest
            order by updated_at desc
            limit ?
            SQL,
            [$member->value, $limit],
        );

        return array_values(array_map(fn (object $row) => new ContinueWatchingItem(
            (string) $row->slug,
            FavoriteKind::from((string) $row->type),
            (string) $row->title,
            $row->release_year === null ? null : (int) $row->release_year,
            $row->poster_path === null ? null : (string) $row->poster_path,
            (string) $row->media_asset_id,
            $row->episode_label === null ? null : (string) $row->episode_label,
            (int) $row->position_seconds,
            (int) $row->duration_seconds,
        ), $rows));
    }
}
