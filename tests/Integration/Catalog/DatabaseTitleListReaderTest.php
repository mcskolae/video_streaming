<?php

declare(strict_types=1);

use HomeFlix\Catalog\Application\Port\TitleListReader;
use HomeFlix\Catalog\Application\Query\AdminTitleRow;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TitleStatus;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogFixtures as F;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->reader = app(TitleListReader::class);
    $this->repository = app(TitleRepository::class);
});

/**
 * @param  list<AdminTitleRow>  $rows
 * @return array<string, AdminTitleRow>
 */
function rowsByName(array $rows): array
{
    $byName = [];

    foreach ($rows as $row) {
        $byName[$row->name] = $row;
    }

    return $byName;
}

/** Enregistre un titre et fixe sa date de création, pour que l'ordre soit déterministe. */
function saveAt(TitleRepository $repository, Title $title, string $createdAt): void
{
    $repository->save($title);
    DB::table('titles')->where('id', $title->id()->value)->update(['created_at' => $createdAt]);
}

it('lists the newest titles first', function () {
    saveAt($this->repository, F::movie(tmdbId: 1, name: 'Ancien'), '2026-01-01 10:00:00');
    saveAt($this->repository, F::movie(tmdbId: 2, name: 'Récent'), '2026-03-01 10:00:00');
    saveAt($this->repository, F::movie(tmdbId: 3, name: 'Milieu'), '2026-02-01 10:00:00');

    $page = $this->reader->adminPage(1, 20);

    expect(array_map(fn ($row) => $row->name, $page->rows))->toBe(['Récent', 'Milieu', 'Ancien'])
        ->and($page->total)->toBe(3);
});

it('paginates', function () {
    foreach (range(1, 25) as $i) {
        saveAt($this->repository, F::movie(tmdbId: $i, name: "Film {$i}"), sprintf('2026-01-%02d 10:00:00', $i));
    }

    $first = $this->reader->adminPage(1, 20);
    $second = $this->reader->adminPage(2, 20);
    $beyond = $this->reader->adminPage(9, 20);

    expect($first->rows)->toHaveCount(20)
        ->and($second->rows)->toHaveCount(5)
        ->and($beyond->rows)->toBe([])
        ->and($first->total)->toBe(25)
        ->and($second->page)->toBe(2)
        ->and($first->rows[0]->name)->toBe('Film 25')
        ->and($second->rows[4]->name)->toBe('Film 1');
});

it('maps every column of a row', function () {
    $movie = F::movie(tmdbId: 10378);
    $movie->markPlayable();
    $movie->publish(F::now());
    $this->repository->save($movie);

    $row = $this->reader->adminPage(1, 20)->rows[0];

    expect($row->id)->toBe($movie->id()->value)
        ->and($row->type)->toBe(TitleType::Movie)
        ->and($row->tmdbId)->toBe(10378)
        ->and($row->name)->toBe('Big Buck Bunny')
        ->and($row->releaseYear)->toBe(2008)
        ->and($row->posterPath)->toBe('/poster.jpg')
        ->and($row->status)->toBe(TitleStatus::Published)
        ->and($row->playable)->toBeTrue();
});

it('says a movie is playable only when its media is ready', function () {
    $ready = F::movie(tmdbId: 1, name: 'Prêt');
    $ready->markPlayable();
    $this->repository->save($ready);
    $this->repository->save(F::movie(tmdbId: 2, name: 'Pas prêt'));

    $byName = rowsByName($this->reader->adminPage(1, 20)->rows);

    expect($byName['Prêt']->playable)->toBeTrue()
        ->and($byName['Pas prêt']->playable)->toBeFalse();
});

it('says a series is playable as soon as one episode is ready', function () {
    $partial = F::series(tmdbId: 1, name: 'Partielle');
    $partial->markEpisodePlayable(1, 2);
    $this->repository->save($partial);
    $this->repository->save(F::series(tmdbId: 2, name: 'Rien'));

    $byName = rowsByName($this->reader->adminPage(1, 20)->rows);

    expect($byName['Partielle']->playable)->toBeTrue()
        ->and($byName['Partielle']->type)->toBe(TitleType::Series)
        ->and($byName['Rien']->playable)->toBeFalse();
});

it('keeps the title when it has no year and no poster', function () {
    $this->repository->save(Title::importMovie(
        F::titleId(),
        new TmdbId(5),
        Slug::fromString('sans-rien'),
        new TitleDetails(name: 'Sans rien'),
    ));

    $row = $this->reader->adminPage(1, 20)->rows[0];

    expect($row->releaseYear)->toBeNull()->and($row->posterPath)->toBeNull();
});

it('returns an empty page for an empty catalog', function () {
    $page = $this->reader->adminPage(1, 20);

    expect($page->rows)->toBe([])->and($page->total)->toBe(0);
});

describe('imported TMDB ids', function () {
    it('returns only the ids already in the catalog, for the given type', function () {
        $this->repository->save(F::movie(tmdbId: 10, name: 'Dix'));
        $this->repository->save(F::series(tmdbId: 20, name: 'Vingt'));

        expect($this->reader->importedTmdbIds(TitleType::Movie, [10, 20, 30]))->toBe([10])
            ->and($this->reader->importedTmdbIds(TitleType::Series, [10, 20, 30]))->toBe([20]);
    });

    it('answers an empty list without querying for no ids', function () {
        DB::enableQueryLog();

        expect($this->reader->importedTmdbIds(TitleType::Movie, []))->toBe([])
            ->and(DB::getQueryLog())->toBe([]);
    });
});
