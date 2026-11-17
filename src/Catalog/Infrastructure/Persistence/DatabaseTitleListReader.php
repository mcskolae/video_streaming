<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Persistence;

use HomeFlix\Catalog\Application\Port\TitleListReader;
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
}
