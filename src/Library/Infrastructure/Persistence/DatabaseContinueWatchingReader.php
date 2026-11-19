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
        return $this->query($member, null, $limit);
    }

    public function forTitle(MemberId $member, string $slug): ?ContinueWatchingItem
    {
        return $this->query($member, $slug, 1)[0] ?? null;
    }

    /** @return list<ContinueWatchingItem> */
    private function query(MemberId $member, ?string $slug, int $limit): array
    {
        // Par titre, la dernière progression (DISTINCT ON), terminée ou non. Si elle est terminée, on propose l'épisode
        // suivant prêt de la série (jointure latérale) ; un film terminé, ou le dernier épisode, ne donne rien.
        // Requête fixe : les valeurs viennent de paramètres liés.
        $rows = DB::select(
            <<<'SQL'
            select * from (
                select distinct on (t.id)
                    t.slug, t.type, t.title, t.release_year, t.poster_path,
                    p.completed, p.updated_at,
                    p.media_asset_id, p.position_seconds, p.duration_seconds,
                    case when e.id is null then null else s.number || 'x' || e.number end as episode_label,
                    nx.media_asset_id as next_media_asset_id, nx.duration_seconds as next_duration_seconds, nx.label as next_label
                from watch_progress p
                join media_assets m on m.id = p.media_asset_id and m.status = 'ready'
                left join episodes e on e.id = m.episode_id
                left join seasons s on s.id = e.season_id
                join titles t on t.id = coalesce(m.title_id, s.title_id) and t.status = 'published'
                left join lateral (
                    select m2.id as media_asset_id, m2.duration_seconds, s2.number || 'x' || e2.number as label
                    from seasons s2
                    join episodes e2 on e2.season_id = s2.id
                    join media_assets m2 on m2.episode_id = e2.id and m2.status = 'ready'
                    where p.completed and e.id is not null and s2.title_id = t.id
                        and (s2.number, e2.number) > (s.number, e.number)
                    order by s2.number, e2.number
                    limit 1
                ) nx on true
                where p.user_id = ? and p.position_seconds > 0 and (?::text is null or t.slug = ?)
                order by t.id, p.updated_at desc
            ) latest
            where not completed or next_media_asset_id is not null
            order by updated_at desc
            limit ?
            SQL,
            [$member->value, $slug, $slug, $limit],
        );

        return array_values(array_map(function (object $row): ContinueWatchingItem {
            $next = (bool) $row->completed;

            return new ContinueWatchingItem(
                (string) $row->slug,
                FavoriteKind::from((string) $row->type),
                (string) $row->title,
                $row->release_year === null ? null : (int) $row->release_year,
                $row->poster_path === null ? null : (string) $row->poster_path,
                (string) ($next ? $row->next_media_asset_id : $row->media_asset_id),
                $next ? (string) $row->next_label : ($row->episode_label === null ? null : (string) $row->episode_label),
                $next ? 0 : (int) $row->position_seconds,
                (int) ($next ? $row->next_duration_seconds : $row->duration_seconds),
                $next,
            );
        }, $rows));
    }
}
