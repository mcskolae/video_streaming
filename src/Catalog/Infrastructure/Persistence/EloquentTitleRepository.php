<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Persistence;

use HomeFlix\Catalog\Domain\Model\EpisodeId;
use HomeFlix\Catalog\Domain\Model\Genre;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleId;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Catalog\Infrastructure\Persistence\Eloquent\EpisodeRecord;
use HomeFlix\Catalog\Infrastructure\Persistence\Eloquent\GenreRecord;
use HomeFlix\Catalog\Infrastructure\Persistence\Eloquent\SeasonRecord;
use HomeFlix\Catalog\Infrastructure\Persistence\Eloquent\TitleRecord;
use HomeFlix\Shared\Application\IdGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class EloquentTitleRepository implements TitleRepository
{
    public function __construct(private IdGenerator $ids) {}

    public function save(Title $title): void
    {
        DB::transaction(function () use ($title): void {
            $record = TitleRecord::query()->updateOrCreate(
                ['id' => $title->id()->value],
                TitleMapper::titleColumns($title),
            );

            $this->syncGenres($record, $title->details()->genres);
            $this->syncSeasons($title);
        });
    }

    public function find(TitleId $id): ?Title
    {
        return $this->toDomain($this->query()->whereKey($id->value)->first());
    }

    public function findByTmdbId(TitleType $type, TmdbId $tmdbId): ?Title
    {
        return $this->toDomain(
            $this->query()->where('type', $type->value)->where('tmdb_id', $tmdbId->value)->first(),
        );
    }

    public function findBySlug(Slug $slug): ?Title
    {
        return $this->toDomain($this->query()->where('slug', $slug->value)->first());
    }

    public function findByEpisodeId(EpisodeId $id): ?Title
    {
        return $this->toDomain($this->query()->whereHas('seasons.episodes', fn ($episodes) => $episodes->whereKey($id->value))->first());
    }

    /** @return Builder<TitleRecord> */
    private function query(): Builder
    {
        return TitleRecord::query()->with(['genres', 'seasons.episodes']);
    }

    private function toDomain(?TitleRecord $record): ?Title
    {
        return $record === null ? null : TitleMapper::toDomain($record);
    }

    /** @param  list<Genre>  $genres */
    private function syncGenres(TitleRecord $record, array $genres): void
    {
        $genreIds = [];

        foreach ($genres as $genre) {
            $genreRecord = GenreRecord::query()->firstOrNew(['tmdb_id' => $genre->tmdbId]);

            if (! $genreRecord->exists) {
                $genreRecord->id = $this->ids->generate();
            }

            $genreRecord->name = $genre->name;
            $genreRecord->save();
            $genreIds[] = $genreRecord->id;
        }

        $record->genres()->sync($genreIds);
    }

    private function syncSeasons(Title $title): void
    {
        $titleId = $title->id()->value;
        $seasonIds = [];

        foreach ($title->seasons() as $season) {
            $seasonIds[] = $season->id->value;

            SeasonRecord::query()->updateOrCreate(
                ['id' => $season->id->value],
                [
                    'title_id' => $titleId,
                    'number' => $season->number,
                    'name' => $season->name,
                    'overview' => $season->overview,
                ],
            );

            $episodeIds = [];

            foreach ($season->episodes() as $episode) {
                $episodeIds[] = $episode->id->value;

                EpisodeRecord::query()->updateOrCreate(
                    ['id' => $episode->id->value],
                    [
                        'season_id' => $season->id->value,
                        'number' => $episode->number,
                        'name' => $episode->name,
                        'overview' => $episode->overview,
                        'runtime_minutes' => $episode->runtimeMinutes,
                        'still_path' => $episode->stillPath,
                        'is_playable' => $episode->isPlayable(),
                    ],
                );
            }

            EpisodeRecord::query()->where('season_id', $season->id->value)->whereNotIn('id', $episodeIds)->delete();
        }

        SeasonRecord::query()->where('title_id', $titleId)->whereNotIn('id', $seasonIds)->delete();
    }
}
