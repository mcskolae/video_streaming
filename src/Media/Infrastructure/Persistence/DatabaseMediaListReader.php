<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Persistence;

use DateTimeImmutable;
use HomeFlix\Media\Application\Port\MediaListReader;
use HomeFlix\Media\Application\Query\AdminMediaRow;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableKind;
use Illuminate\Support\Facades\DB;

/** Lecture directe en SQL, en trois requêtes quel que soit le nombre de vidéos. */
final class DatabaseMediaListReader implements MediaListReader
{
    public function recent(int $limit): array
    {
        $rows = DB::table('media_assets')
            ->leftJoin('titles', 'titles.id', '=', 'media_assets.title_id')
            ->leftJoin('episodes', 'episodes.id', '=', 'media_assets.episode_id')
            ->leftJoin('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->leftJoin('titles as series', 'series.id', '=', 'seasons.title_id')
            ->orderByDesc('media_assets.updated_at')
            ->orderByDesc('media_assets.id')
            ->limit(max(1, $limit))
            ->get([
                'media_assets.id', 'media_assets.title_id', 'media_assets.episode_id', 'media_assets.status',
                'media_assets.duration_seconds', 'media_assets.error_message', 'media_assets.updated_at',
                'titles.title as movie', 'series.title as series', 'seasons.number as season', 'episodes.number as episode',
            ]);

        $renditions = DB::table('renditions')
            ->whereIn('media_asset_id', $rows->pluck('id')->all())
            ->orderBy('height')
            ->get(['media_asset_id', 'label'])
            ->groupBy('media_asset_id');

        return $rows->map(function (object $row) use ($renditions): AdminMediaRow {
            $isMovie = $row->title_id !== null;

            return new AdminMediaRow(
                (string) $row->id,
                $isMovie ? PlayableKind::Title : PlayableKind::Episode,
                (string) ($isMovie ? $row->title_id : $row->episode_id),
                $isMovie ? (string) $row->movie : sprintf('%s, S%dE%d', $row->series, $row->season, $row->episode),
                MediaStatus::from((string) $row->status),
                $row->duration_seconds === null ? null : (int) $row->duration_seconds,
                $row->error_message === null ? null : (string) $row->error_message,
                ($renditions[$row->id] ?? collect())->map(fn (object $r): string => (string) $r->label)->values()->all(),
                new DateTimeImmutable((string) $row->updated_at),
            );
        })->values()->all();
    }
}
