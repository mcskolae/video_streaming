<?php

declare(strict_types=1);

namespace HomeFlix\Library\Infrastructure\Persistence;

use HomeFlix\Library\Application\Port\FavoritesReader;
use HomeFlix\Library\Application\Query\FavoriteCard;
use HomeFlix\Library\Application\Query\FavoriteKind;
use HomeFlix\Library\Domain\Model\MemberId;
use Illuminate\Support\Facades\DB;

/** Une seule requête, qui ne retient que les titres publiés. */
final class DatabaseFavoritesReader implements FavoritesReader
{
    public function publishedFor(MemberId $member): array
    {
        return DB::table('favorites')
            ->join('titles', 'titles.id', '=', 'favorites.title_id')
            ->where('favorites.user_id', $member->value)
            ->where('titles.status', 'published')
            ->orderByDesc('favorites.created_at')
            ->orderByDesc('titles.id')
            ->get(['titles.slug', 'titles.type', 'titles.title', 'titles.release_year', 'titles.poster_path'])
            ->map(fn (object $row) => new FavoriteCard(
                (string) $row->slug,
                FavoriteKind::from((string) $row->type),
                (string) $row->title,
                $row->release_year === null ? null : (int) $row->release_year,
                $row->poster_path === null ? null : (string) $row->poster_path,
            ))
            ->values()
            ->all();
    }
}
