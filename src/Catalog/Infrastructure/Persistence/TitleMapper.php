<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Persistence;

use HomeFlix\Catalog\Domain\Model\Episode;
use HomeFlix\Catalog\Domain\Model\EpisodeId;
use HomeFlix\Catalog\Domain\Model\Genre;
use HomeFlix\Catalog\Domain\Model\Season;
use HomeFlix\Catalog\Domain\Model\SeasonId;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TitleId;
use HomeFlix\Catalog\Domain\Model\TitleStatus;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use HomeFlix\Catalog\Infrastructure\Persistence\Eloquent\EpisodeRecord;
use HomeFlix\Catalog\Infrastructure\Persistence\Eloquent\GenreRecord;
use HomeFlix\Catalog\Infrastructure\Persistence\Eloquent\SeasonRecord;
use HomeFlix\Catalog\Infrastructure\Persistence\Eloquent\TitleRecord;

/** Convertit entre l'agrégat Title et les lignes Eloquent. */
final class TitleMapper
{
    /** Le record doit être chargé avec `genres` et `seasons.episodes`. */
    public static function toDomain(TitleRecord $record): Title
    {
        $genres = $record->genres
            ->map(fn (GenreRecord $genre) => new Genre($genre->tmdb_id, $genre->name))
            ->values()
            ->all();

        $seasons = $record->seasons
            ->map(fn (SeasonRecord $season) => new Season(
                SeasonId::fromString($season->id),
                $season->number,
                $season->name,
                $season->overview,
                $season->episodes
                    ->map(fn (EpisodeRecord $episode) => new Episode(
                        EpisodeId::fromString($episode->id),
                        $episode->number,
                        $episode->name,
                        $episode->overview,
                        $episode->runtime_minutes,
                        $episode->still_path,
                        $episode->is_playable,
                    ))
                    ->values()
                    ->all(),
            ))
            ->values()
            ->all();

        return Title::reconstitute(
            TitleId::fromString($record->id),
            TitleType::from($record->type),
            new TmdbId($record->tmdb_id),
            Slug::fromString($record->slug),
            new TitleDetails(
                name: $record->title,
                originalName: $record->original_title,
                overview: $record->overview,
                releaseYear: $record->release_year,
                runtimeMinutes: $record->runtime_minutes,
                posterPath: $record->poster_path,
                backdropPath: $record->backdrop_path,
                genres: $genres,
            ),
            TitleStatus::from($record->status),
            $record->published_at?->toDateTimeImmutable(),
            $record->is_playable,
            $seasons,
        );
    }

    /** @return array<string, mixed> colonnes de la table `titles` */
    public static function titleColumns(Title $title): array
    {
        $details = $title->details();

        return [
            'type' => $title->type()->value,
            'tmdb_id' => $title->tmdbId()->value,
            'slug' => $title->slug()->value,
            'title' => $details->name,
            'original_title' => $details->originalName,
            'overview' => $details->overview,
            'release_year' => $details->releaseYear,
            'runtime_minutes' => $details->runtimeMinutes,
            'poster_path' => $details->posterPath,
            'backdrop_path' => $details->backdropPath,
            'status' => $title->status()->value,
            'published_at' => $title->publishedAt(),
            'is_playable' => $title->playableFlag(),
        ];
    }
}
