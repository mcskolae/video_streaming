<?php

declare(strict_types=1);

use HomeFlix\Catalog\Domain\Model\Genre;
use HomeFlix\Catalog\Domain\Model\Season;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TitleStatus;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Catalog\Infrastructure\Persistence\Eloquent\GenreRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogFixtures as F;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(TitleRepository::class);
});

it('saves and reloads a complete movie', function () {
    $movie = F::movie();
    $this->repository->save($movie);

    $loaded = $this->repository->find($movie->id());

    expect($loaded)->not->toBeNull()
        ->and($loaded->id()->equals($movie->id()))->toBeTrue()
        ->and($loaded->type())->toBe(TitleType::Movie)
        ->and($loaded->tmdbId()->value)->toBe(10378)
        ->and($loaded->slug()->value)->toBe('big-buck-bunny-2008')
        ->and($loaded->status())->toBe(TitleStatus::Draft)
        ->and($loaded->details())->toEqual($movie->details())
        ->and($loaded->seasons())->toBe([]);
});

it('saves and reloads a complete series with seasons, episodes and flags', function () {
    $series = F::series();
    $series->addSeason(F::seasonId(), 0, 'Spéciaux');
    $series->markEpisodePlayable(1, 2);
    $this->repository->save($series);

    $loaded = $this->repository->find($series->id());

    expect($loaded->type())->toBe(TitleType::Series)
        ->and(array_map(fn (Season $s) => $s->number, $loaded->seasons()))->toBe([0, 1])
        ->and($loaded->season(1)->name)->toBe('Saison 1')
        ->and(array_map(fn ($e) => $e->number, $loaded->season(1)->episodes()))->toBe([1, 2])
        ->and($loaded->season(1)->episode(1)->name)->toBe('Pilote')
        ->and($loaded->season(1)->episode(1)->runtimeMinutes)->toBe(24)
        ->and($loaded->season(1)->episode(1)->stillPath)->toBe('/still1.jpg')
        ->and($loaded->season(1)->episode(1)->isPlayable())->toBeFalse()
        ->and($loaded->season(1)->episode(2)->isPlayable())->toBeTrue()
        ->and($loaded->isPlayable())->toBeTrue()
        ->and($loaded->season(1)->id->equals($series->season(1)->id))->toBeTrue();
});

it('returns null for an unknown title', function () {
    expect($this->repository->find(F::titleId()))->toBeNull()
        ->and($this->repository->findByTmdbId(TitleType::Movie, new TmdbId(999)))->toBeNull()
        ->and($this->repository->findBySlug(Slug::fromString('inconnu')))->toBeNull();
});

it('finds a title by TMDB id within its type and by slug', function () {
    $movie = F::movie(tmdbId: 42, name: 'Film');
    $series = F::series(tmdbId: 42, name: 'Série');
    $this->repository->save($movie);
    $this->repository->save($series);

    expect($this->repository->findByTmdbId(TitleType::Movie, new TmdbId(42))->id()->equals($movie->id()))->toBeTrue()
        ->and($this->repository->findByTmdbId(TitleType::Series, new TmdbId(42))->id()->equals($series->id()))->toBeTrue()
        ->and($this->repository->findBySlug($series->slug())->id()->equals($series->id()))->toBeTrue();
});

it('persists publication and unpublication', function () {
    $movie = F::movie();
    $movie->markPlayable();
    $movie->publish(F::now());
    $this->repository->save($movie);

    $published = $this->repository->find($movie->id());
    expect($published->isPublished())->toBeTrue()
        ->and($published->publishedAt())->toEqual(F::now())
        ->and($published->isPlayable())->toBeTrue();

    $published->unpublish(F::now());
    $this->repository->save($published);

    $draft = $this->repository->find($movie->id());
    expect($draft->isPublished())->toBeFalse()
        ->and($draft->publishedAt())->toBeNull();
});

it('updates an existing title in place instead of duplicating it', function () {
    $movie = F::movie();
    $this->repository->save($movie);

    $movie->refreshDetails(new TitleDetails(name: 'Big Buck Bunny HD', releaseYear: 2008, genres: [new Genre(16, 'Animation')]));
    $this->repository->save($movie);

    $loaded = $this->repository->find($movie->id());
    expect(DB::table('titles')->count())->toBe(1)
        ->and($loaded->details()->name)->toBe('Big Buck Bunny HD')
        ->and($loaded->details()->genres)->toHaveCount(1);
});

it('persists episodes added later and drops those removed from the aggregate', function () {
    $series = F::series();
    $this->repository->save($series);

    $series->addEpisode(1, F::episodeId(), 3, 'Final');
    $this->repository->save($series);
    expect(DB::table('episodes')->count())->toBe(3);

    $withoutLast = Title::reconstitute(
        $series->id(), $series->type(), $series->tmdbId(), $series->slug(), $series->details(),
        $series->status(), $series->publishedAt(), $series->playableFlag(),
        [new Season($series->season(1)->id, 1, 'Saison 1', '', [$series->season(1)->episode(1)])],
    );
    $this->repository->save($withoutLast);

    expect(DB::table('episodes')->count())->toBe(1)
        ->and($this->repository->find($series->id())->season(1)->episodes())->toHaveCount(1);
});

it('removes seasons and their episodes that are no longer in the aggregate', function () {
    $series = F::series();
    $this->repository->save($series);

    $this->repository->save(Title::reconstitute(
        $series->id(), $series->type(), $series->tmdbId(), $series->slug(), $series->details(),
        $series->status(), $series->publishedAt(), $series->playableFlag(), [],
    ));

    expect(DB::table('seasons')->count())->toBe(0)
        ->and(DB::table('episodes')->count())->toBe(0);
});

it('shares a genre between titles and keeps one row per TMDB genre', function () {
    $this->repository->save(F::movie(tmdbId: 1, name: 'Un'));
    $this->repository->save(F::movie(tmdbId: 2, name: 'Deux'));

    expect(GenreRecord::query()->count())->toBe(2)
        ->and(DB::table('title_genre')->count())->toBe(4);
});

it('renames a genre when TMDB changes its label', function () {
    $this->repository->save(F::movie(tmdbId: 1, name: 'Un'));
    $other = F::movie(tmdbId: 2, name: 'Deux');
    $other->refreshDetails(new TitleDetails(name: 'Deux', genres: [new Genre(16, 'Dessin animé')]));
    $this->repository->save($other);

    expect(GenreRecord::query()->where('tmdb_id', 16)->value('name'))->toBe('Dessin animé');
});

it('generates version 7 UUIDs for genres', function () {
    $this->repository->save(F::movie());

    $id = GenreRecord::query()->where('tmdb_id', 16)->value('id');

    expect(F::uuidVersion($id))->toBe(7);
});

it('uses version 7 UUIDs for the identifiers it stores', function () {
    $series = F::series();
    $this->repository->save($series);

    foreach (['titles', 'seasons', 'episodes'] as $table) {
        foreach (DB::table($table)->pluck('id') as $id) {
            expect(F::uuidVersion($id))->toBe(7);
        }
    }
});
