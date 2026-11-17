<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Persistence;

use HomeFlix\Catalog\Application\Port\TitleListReader;
use HomeFlix\Catalog\Application\Query\AdminEpisode;
use HomeFlix\Catalog\Application\Query\AdminSeason;
use HomeFlix\Catalog\Application\Query\AdminSeries;
use HomeFlix\Catalog\Application\Query\AdminTitlePage;
use HomeFlix\Catalog\Application\Query\AdminTitleRow;
use HomeFlix\Catalog\Domain\Model\TitleStatus;
use HomeFlix\Catalog\Domain\Model\TitleType;
use Illuminate\Support\Facades\DB;

/** Lecture directe en SQL : une liste n'a pas besoin de reconstruire les agrégats. */
final class DatabaseTitleListReader implements TitleListReader
{
    public function adminPage(int $page, int $perPage): AdminTitlePage
    {
        $rows = DB::table('titles')
            ->select(['id', 'type', 'tmdb_id', 'title', 'release_year', 'poster_path', 'status', 'is_playable'])
            // Une série est lisible quand au moins un de ses épisodes l'est. Requête fixe, sans entrée utilisateur.
            ->selectRaw('exists (select 1 from episodes e join seasons s on s.id = e.season_id where s.title_id = titles.id and e.is_playable) as has_playable_episode')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        return new AdminTitlePage(
            $rows->map(function (object $row): AdminTitleRow {
                $type = TitleType::from((string) $row->type);

                return new AdminTitleRow(
                    (string) $row->id,
                    $type,
                    (int) $row->tmdb_id,
                    (string) $row->title,
                    $row->release_year === null ? null : (int) $row->release_year,
                    $row->poster_path === null ? null : (string) $row->poster_path,
                    TitleStatus::from((string) $row->status),
                    $type === TitleType::Movie ? (bool) $row->is_playable : (bool) $row->has_playable_episode,
                );
            })->values()->all(),
            DB::table('titles')->count(),
            $page,
            $perPage,
        );
    }

    public function importedTmdbIds(TitleType $type, array $tmdbIds): array
    {
        if ($tmdbIds === []) {
            return [];
        }

        return DB::table('titles')
            ->where('type', $type->value)
            ->whereIn('tmdb_id', $tmdbIds)
            ->pluck('tmdb_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function adminSeries(string $titleId): ?AdminSeries
    {
        $title = DB::table('titles')
            ->where('id', $titleId)
            ->where('type', TitleType::Series->value)
            ->first(['id', 'title', 'status']);

        if ($title === null) {
            return null;
        }

        $rows = DB::table('seasons')
            ->leftJoin('episodes', 'episodes.season_id', '=', 'seasons.id')
            ->where('seasons.title_id', $titleId)
            ->orderBy('seasons.number')
            ->orderBy('episodes.number')
            ->get([
                'seasons.id as season_id', 'seasons.number as season_number', 'seasons.name as season_name',
                'episodes.id as episode_id', 'episodes.number as episode_number', 'episodes.name as episode_name',
                'episodes.is_playable as episode_playable',
            ]);

        $seasons = [];

        foreach ($rows as $row) {
            $key = (string) $row->season_id;
            $seasons[$key] ??= ['number' => (int) $row->season_number, 'name' => (string) $row->season_name, 'episodes' => []];

            if ($row->episode_id !== null) {
                $seasons[$key]['episodes'][] = new AdminEpisode(
                    (string) $row->episode_id,
                    (int) $row->episode_number,
                    (string) $row->episode_name,
                    (bool) $row->episode_playable,
                );
            }
        }

        return new AdminSeries(
            (string) $title->id,
            (string) $title->title,
            TitleStatus::from((string) $title->status),
            array_values(array_map(fn (array $s) => new AdminSeason($s['number'], $s['name'], $s['episodes']), $seasons)),
        );
    }
}
