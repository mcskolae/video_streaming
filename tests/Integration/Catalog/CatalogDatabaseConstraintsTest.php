<?php

declare(strict_types=1);

use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogFixtures as F;

/*
 * La base de données doit refuser seule les états incohérents, même si le code
 * de l'application a un défaut. Ces tests contournent le domaine exprès, et chacun
 * vérifie le nom de la contrainte qui a refusé la ligne, pour ne pas réussir
 * à cause d'une autre erreur.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(TitleRepository::class);
});

/**
 * Exécute une instruction dans un point de sauvegarde, pour ne pas casser la transaction du test.
 *
 * @param  Closure(): mixed  $statement
 */
function attempt(Closure $statement): void
{
    DB::transaction($statement);
}

describe('unique constraints', function () {
    it('refuses two titles with the same type and TMDB id', function () {
        $this->repository->save(F::movie(tmdbId: 7, name: 'Premier'));

        expect(fn () => attempt(fn () => $this->repository->save(F::movie(tmdbId: 7, name: 'Second'))))
            ->toThrow(UniqueConstraintViolationException::class, 'titles_type_tmdb_id_unique');
    });

    it('accepts the same TMDB id for a movie and a series', function () {
        $this->repository->save(F::movie(tmdbId: 7, name: 'Film'));
        $this->repository->save(F::series(tmdbId: 7, name: 'Série'));

        expect(DB::table('titles')->count())->toBe(2);
    });

    it('refuses two titles with the same slug', function () {
        $this->repository->save(F::movie(tmdbId: 1, name: 'Même nom'));

        expect(fn () => attempt(fn () => $this->repository->save(F::movie(tmdbId: 2, name: 'Même nom'))))
            ->toThrow(UniqueConstraintViolationException::class, 'titles_slug_unique');
    });

    it('refuses two seasons with the same number in a title', function () {
        $series = F::series();
        $this->repository->save($series);

        expect(fn () => attempt(fn () => DB::table('seasons')->insert([
            'id' => F::seasonId()->value, 'title_id' => $series->id()->value, 'number' => 1,
        ])))->toThrow(UniqueConstraintViolationException::class, 'seasons_title_id_number_unique');
    });

    it('refuses two episodes with the same number in a season', function () {
        $series = F::series();
        $this->repository->save($series);

        expect(fn () => attempt(fn () => DB::table('episodes')->insert([
            'id' => F::episodeId()->value, 'season_id' => $series->season(1)->id->value, 'number' => 1,
        ])))->toThrow(UniqueConstraintViolationException::class, 'episodes_season_id_number_unique');
    });

    it('accepts the same season number in two different titles', function () {
        $this->repository->save(F::series(tmdbId: 1, name: 'A'));
        $this->repository->save(F::series(tmdbId: 2, name: 'B'));

        expect(DB::table('seasons')->where('number', 1)->count())->toBe(2);
    });
});

describe('check constraints', function () {
    it('refuses invalid values', function (string $table, string $row, array $changes, string $constraint) {
        $series = F::series();
        $movie = F::movie();
        $this->repository->save($series);
        $this->repository->save($movie);

        $ids = [
            'movie' => $movie->id()->value,
            'series' => $series->id()->value,
            'season' => $series->season(1)->id->value,
            'episode' => $series->season(1)->episode(1)->id->value,
        ];

        expect(fn () => attempt(fn () => DB::table($table)->where('id', $ids[$row])->update($changes)))
            ->toThrow(QueryException::class, $constraint);
    })->with([
        'release year before cinema' => ['titles', 'movie', ['release_year' => 1800], 'titles_release_year_range'],
        'release year too far' => ['titles', 'movie', ['release_year' => 2200], 'titles_release_year_range'],
        'zero runtime' => ['titles', 'movie', ['runtime_minutes' => 0], 'titles_runtime_positive'],
        'published without date' => ['titles', 'movie', ['status' => 'published', 'is_playable' => true], 'titles_published_has_date'],
        'date without published status' => ['titles', 'movie', ['published_at' => '2026-11-17 12:00:00'], 'titles_published_has_date'],
        'published movie not playable' => ['titles', 'movie', ['status' => 'published', 'published_at' => '2026-11-17 12:00:00', 'is_playable' => false], 'titles_published_movie_playable'],
        'series flagged playable' => ['titles', 'series', ['is_playable' => true], 'titles_series_not_flagged'],
        'unknown status' => ['titles', 'movie', ['status' => 'archived'], 'titles_status_check'],
        'unknown type' => ['titles', 'movie', ['type' => 'documentary'], 'titles_type_check'],
        'negative season number' => ['seasons', 'season', ['number' => -1], 'seasons_number_range'],
        'season number too high' => ['seasons', 'season', ['number' => 1000], 'seasons_number_range'],
        'episode number zero' => ['episodes', 'episode', ['number' => 0], 'episodes_number_positive'],
        'episode runtime zero' => ['episodes', 'episode', ['runtime_minutes' => 0], 'episodes_runtime_positive'],
    ]);

    it('accepts a published playable movie', function () {
        $movie = F::movie();
        $this->repository->save($movie);

        DB::table('titles')->where('id', $movie->id()->value)->update([
            'status' => 'published', 'published_at' => '2026-11-17 12:00:00', 'is_playable' => true,
        ]);

        expect(DB::table('titles')->where('status', 'published')->count())->toBe(1);
    });

    it('accepts season 0 for special episodes', function () {
        $series = F::series();
        $this->repository->save($series);

        DB::table('seasons')->insert(['id' => F::seasonId()->value, 'title_id' => $series->id()->value, 'number' => 0]);

        expect(DB::table('seasons')->where('number', 0)->count())->toBe(1);
    });
});

describe('referential integrity', function () {
    it('deletes seasons, episodes and genre links with their title', function () {
        $series = F::series();
        $this->repository->save($series);

        DB::table('titles')->where('id', $series->id()->value)->delete();

        expect(DB::table('seasons')->count())->toBe(0)
            ->and(DB::table('episodes')->count())->toBe(0)
            ->and(DB::table('title_genre')->count())->toBe(0)
            ->and(DB::table('genres')->count())->toBe(2);
    });

    it('refuses a season attached to an unknown title', function () {
        expect(fn () => attempt(fn () => DB::table('seasons')->insert([
            'id' => F::seasonId()->value, 'title_id' => F::titleId()->value, 'number' => 1,
        ])))->toThrow(QueryException::class, 'seasons_title_id_foreign');
    });

    it('refuses to delete a genre still used by a title', function () {
        $this->repository->save(F::movie());

        expect(fn () => attempt(fn () => DB::table('genres')->where('tmdb_id', 16)->delete()))
            ->toThrow(QueryException::class, 'title_genre_genre_id_foreign');
    });
});

describe('search index', function () {
    it('has the trigram index and extension used by the search', function () {
        $extension = DB::selectOne("SELECT extname FROM pg_extension WHERE extname = 'pg_trgm'");
        $index = DB::selectOne("SELECT indexname FROM pg_indexes WHERE indexname = 'titles_title_trgm_idx'");

        expect($extension)->not->toBeNull()->and($index)->not->toBeNull();
    });

    it('finds a title despite a typing mistake', function () {
        $this->repository->save(F::movie(tmdbId: 1, name: 'Big Buck Bunny'));
        $this->repository->save(F::movie(tmdbId: 2, name: 'Sintel'));

        $found = DB::table('titles')->whereRaw('title % ?', ['Big Bukc Bunny'])->pluck('title')->all();

        expect($found)->toBe(['Big Buck Bunny']);
    });
});
