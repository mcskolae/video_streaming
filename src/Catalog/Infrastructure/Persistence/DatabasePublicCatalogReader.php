<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Persistence;

use DateTimeImmutable;
use HomeFlix\Catalog\Application\Port\PublicCatalogReader;
use HomeFlix\Catalog\Application\Query\Public\PublicEpisode;
use HomeFlix\Catalog\Application\Query\Public\PublicGenre;
use HomeFlix\Catalog\Application\Query\Public\PublicSeason;
use HomeFlix\Catalog\Application\Query\Public\PublicSitemapEntry;
use HomeFlix\Catalog\Application\Query\Public\PublicTitleCard;
use HomeFlix\Catalog\Application\Query\Public\PublicTitleDetails;
use HomeFlix\Catalog\Application\Query\Public\PublicTitlePage;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\TitleType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

/**
 * Lecture directe en SQL du catalogue public. Toutes les requêtes filtrent sur `status = 'published'` :
 * un brouillon n'apparaît jamais ici.
 */
final class DatabasePublicCatalogReader implements PublicCatalogReader
{
    private const CARD_COLUMNS = ['titles.slug', 'titles.type', 'titles.title', 'titles.release_year', 'titles.poster_path'];

    public function latest(int $limit): array
    {
        return $this->cards(
            $this->published()
                ->orderByDesc('titles.published_at')
                ->orderByDesc('titles.id')
                ->limit(max(1, $limit)),
        );
    }

    public function genres(): array
    {
        return $this->genreQuery()
            ->orderBy('genres.name')
            ->get()
            ->map(fn (object $row) => $this->toGenre($row))
            ->values()
            ->all();
    }

    public function genre(int $tmdbId): ?PublicGenre
    {
        $row = $this->genreQuery()->where('genres.tmdb_id', $tmdbId)->first();

        return $row === null ? null : $this->toGenre($row);
    }

    public function byGenre(int $genreTmdbId, int $page, int $perPage): PublicTitlePage
    {
        $base = fn (): Builder => $this->published()
            ->join('title_genre', 'title_genre.title_id', '=', 'titles.id')
            ->join('genres', 'genres.id', '=', 'title_genre.genre_id')
            ->where('genres.tmdb_id', $genreTmdbId);

        return new PublicTitlePage(
            $this->cards($base()->orderByDesc('titles.published_at')->orderByDesc('titles.id')->forPage($page, $perPage)),
            $base()->count(),
            $page,
            $perPage,
        );
    }

    public function search(string $term, int $limit): array
    {
        // Les caractères spéciaux de LIKE sont neutralisés : le terme est du texte, pas un motif.
        $like = '%'.addcslashes($term, '\\%_').'%';
        $slugTerm = $this->slugOf($term);
        $slugLike = $slugTerm === null ? null : '%'.addcslashes($slugTerm, '\\%_').'%';

        $query = $this->published()->where(function (Builder $where) use ($term, $like, $slugTerm, $slugLike): void {
            // `%` : similarité par trigrammes (tolère une faute de frappe). ILIKE : sous-chaîne, utile aux termes très courts.
            $where->whereRaw('titles.title % ?', [$term])
                ->orWhereRaw("titles.title ILIKE ? ESCAPE '\\'", [$like]);

            if ($slugTerm !== null && $slugLike !== null) {
                $where->orWhereRaw('titles.slug % ?', [$slugTerm])
                    ->orWhereRaw("titles.slug ILIKE ? ESCAPE '\\'", [$slugLike]);
            }
        });

        return $this->cards(
            $query
                ->orderByRaw('GREATEST(similarity(titles.title, ?), similarity(titles.slug, ?)) DESC', [$term, $slugTerm ?? ''])
                ->orderBy('titles.title')
                ->orderBy('titles.id')
                ->limit(max(1, $limit)),
        );
    }

    public function bySlug(string $slug): ?PublicTitleDetails
    {
        // Le flux n'est exposé que si le média est prêt (jointure sur le statut), jamais pendant l'envoi ou la conversion.
        $title = $this->published()
            ->leftJoin('media_assets', function ($join): void {
                $join->on('media_assets.title_id', '=', 'titles.id')->where('media_assets.status', '=', 'ready');
            })
            ->where('titles.slug', $slug)
            ->first(['titles.id', 'titles.slug', 'titles.type', 'titles.title', 'titles.original_title', 'titles.overview',
                'titles.release_year', 'titles.runtime_minutes', 'titles.poster_path', 'titles.backdrop_path',
                'media_assets.hls_master_path as stream_path']);

        if ($title === null) {
            return null;
        }

        $titleId = (string) $title->id;

        $genres = DB::table('genres')
            ->join('title_genre', 'title_genre.genre_id', '=', 'genres.id')
            ->where('title_genre.title_id', $titleId)
            ->orderBy('genres.name')
            ->get(['genres.tmdb_id', 'genres.name'])
            ->map(fn (object $row) => new PublicGenre((int) $row->tmdb_id, (string) $row->name, 0))
            ->values()
            ->all();

        $type = TitleType::from((string) $title->type);

        return new PublicTitleDetails(
            $titleId,
            (string) $title->slug,
            $type,
            (string) $title->title,
            $title->original_title === null ? null : (string) $title->original_title,
            (string) $title->overview,
            $title->release_year === null ? null : (int) $title->release_year,
            $title->runtime_minutes === null ? null : (int) $title->runtime_minutes,
            $title->poster_path === null ? null : (string) $title->poster_path,
            $title->backdrop_path === null ? null : (string) $title->backdrop_path,
            $genres,
            $type === TitleType::Series ? $this->seasons($titleId) : [],
            $this->streamPath($title->stream_path),
        );
    }

    public function sitemap(int $limit): array
    {
        return $this->published()
            ->orderByDesc('titles.published_at')
            ->orderByDesc('titles.id')
            ->limit(max(1, $limit))
            ->get(['titles.slug', DB::raw('COALESCE(titles.updated_at, titles.published_at) as last_modified')])
            ->map(fn (object $row) => new PublicSitemapEntry((string) $row->slug, new DateTimeImmutable((string) $row->last_modified)))
            ->values()
            ->all();
    }

    /** @return Builder titres publiés uniquement */
    private function published(): Builder
    {
        return DB::table('titles')->where('titles.status', 'published');
    }

    /** @return Builder genres ayant au moins un titre publié, avec leur nombre de titres */
    private function genreQuery(): Builder
    {
        return DB::table('genres')
            ->join('title_genre', 'title_genre.genre_id', '=', 'genres.id')
            ->join('titles', function ($join): void {
                $join->on('titles.id', '=', 'title_genre.title_id')->where('titles.status', '=', 'published');
            })
            ->groupBy('genres.id', 'genres.tmdb_id', 'genres.name')
            ->select(['genres.tmdb_id', 'genres.name'])
            ->selectRaw('count(*) as title_count');
    }

    private function toGenre(object $row): PublicGenre
    {
        return new PublicGenre((int) $row->tmdb_id, (string) $row->name, (int) $row->title_count);
    }

    /** @return list<PublicTitleCard> */
    private function cards(Builder $query): array
    {
        return $query->get(self::CARD_COLUMNS)
            ->map(fn (object $row) => new PublicTitleCard(
                (string) $row->slug,
                TitleType::from((string) $row->type),
                (string) $row->title,
                $row->release_year === null ? null : (int) $row->release_year,
                $row->poster_path === null ? null : (string) $row->poster_path,
            ))
            ->values()
            ->all();
    }

    /**
     * Saisons et épisodes d'une série en une seule requête, pas une par saison.
     *
     * @return list<PublicSeason>
     */
    private function seasons(string $titleId): array
    {
        $rows = DB::table('seasons')
            ->leftJoin('episodes', 'episodes.season_id', '=', 'seasons.id')
            ->leftJoin('media_assets', function ($join): void {
                $join->on('media_assets.episode_id', '=', 'episodes.id')->where('media_assets.status', '=', 'ready');
            })
            ->where('seasons.title_id', $titleId)
            ->orderBy('seasons.number')
            ->orderBy('episodes.number')
            ->get([
                'seasons.id as season_id', 'seasons.number as season_number', 'seasons.name as season_name', 'seasons.overview as season_overview',
                'episodes.number as episode_number', 'episodes.name as episode_name', 'episodes.overview as episode_overview',
                'episodes.runtime_minutes as episode_runtime', 'episodes.still_path as episode_still', 'episodes.is_playable as episode_playable',
                'media_assets.hls_master_path as episode_stream',
            ]);

        /** @var array<string, array{season: stdClass, episodes: list<PublicEpisode>}> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(string) $row->season_id] ??= ['season' => $row, 'episodes' => []];

            if ($row->episode_number !== null) {
                $grouped[(string) $row->season_id]['episodes'][] = new PublicEpisode(
                    (int) $row->episode_number,
                    (string) $row->episode_name,
                    (string) $row->episode_overview,
                    $row->episode_runtime === null ? null : (int) $row->episode_runtime,
                    $row->episode_still === null ? null : (string) $row->episode_still,
                    (bool) $row->episode_playable,
                    $this->streamPath($row->episode_stream),
                );
            }
        }

        return array_values(array_map(
            fn (array $group) => new PublicSeason(
                (int) $group['season']->season_number,
                (string) $group['season']->season_name,
                (string) $group['season']->season_overview,
                $group['episodes'],
            ),
            $grouped,
        ));
    }

    /**
     * N'accepte que le format exact d'un chemin de flux produit par le transcodage : un autre contenu en base
     * ne devient jamais une URL de lecture.
     */
    private function streamPath(mixed $path): ?string
    {
        return is_string($path) && preg_match('#^hls/[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/master\.m3u8$#', $path) === 1 ? $path : null;
    }

    /** Le terme tel qu'il serait écrit dans un slug ("Amélie" donne "amelie"), ou null s'il n'a ni lettre ni chiffre. */
    private function slugOf(string $term): ?string
    {
        try {
            return Slug::fromTitle($term)->value;
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
