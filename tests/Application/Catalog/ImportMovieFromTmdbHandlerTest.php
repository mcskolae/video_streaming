<?php

declare(strict_types=1);

use HomeFlix\Catalog\Application\Command\ImportMovieFromTmdb;
use HomeFlix\Catalog\Application\Command\ImportMovieFromTmdbHandler;
use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Domain\Model\Genre;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TitleStatus;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use Tests\Support\Catalog\FakeMetadataProvider;
use Tests\Support\Catalog\InMemoryTitleRepository;
use Tests\Support\CatalogFixtures as F;
use Tests\Support\SpyEventDispatcher;
use Tests\Support\UuidV7Generator;

beforeEach(function () {
    $this->titles = new InMemoryTitleRepository;
    $this->metadata = (new FakeMetadataProvider)->withMovie(10378, F::details('Big Buck Bunny', 2008));
    $this->events = new SpyEventDispatcher;
    $this->handler = new ImportMovieFromTmdbHandler($this->titles, $this->metadata, new UuidV7Generator, $this->events);
});

it('imports a movie as an unplayable draft', function () {
    $result = $this->handler->handle(new ImportMovieFromTmdb(10378));

    $title = $this->titles->find($result->titleId);
    expect($result->created)->toBeTrue()
        ->and($title)->not->toBeNull()
        ->and($title->type())->toBe(TitleType::Movie)
        ->and($title->status())->toBe(TitleStatus::Draft)
        ->and($title->isPlayable())->toBeFalse()
        ->and($title->tmdbId()->value)->toBe(10378)
        ->and($title->slug()->value)->toBe('big-buck-bunny-2008')
        ->and($title->details()->name)->toBe('Big Buck Bunny')
        ->and($title->details()->genres)->toHaveCount(2)
        ->and($this->titles->count())->toBe(1);
});

it('does not create a duplicate when the movie is imported twice', function () {
    $first = $this->handler->handle(new ImportMovieFromTmdb(10378));
    $second = $this->handler->handle(new ImportMovieFromTmdb(10378));

    expect($this->titles->count())->toBe(1)
        ->and($second->created)->toBeFalse()
        ->and($second->titleId->equals($first->titleId))->toBeTrue();
});

it('updates the details of an existing movie and keeps its state', function () {
    $first = $this->handler->handle(new ImportMovieFromTmdb(10378));
    $title = $this->titles->find($first->titleId);
    $title->markPlayable();
    $title->publish(F::now());
    $this->titles->save($title);

    $this->metadata->withMovie(10378, new TitleDetails(
        name: 'Big Buck Bunny (restauré)', overview: 'Nouveau synopsis.', releaseYear: 2008, genres: [new Genre(16, 'Animation')],
    ));
    $this->handler->handle(new ImportMovieFromTmdb(10378));

    $reloaded = $this->titles->find($first->titleId);
    expect($reloaded->details()->name)->toBe('Big Buck Bunny (restauré)')
        ->and($reloaded->details()->overview)->toBe('Nouveau synopsis.')
        ->and($reloaded->details()->genres)->toHaveCount(1)
        ->and($reloaded->isPublished())->toBeTrue()
        ->and($reloaded->slug()->value)->toBe('big-buck-bunny-2008')
        ->and($this->titles->count())->toBe(1);
});

it('does not mistake a series for the movie with the same TMDB id', function () {
    $this->titles->save(F::series(tmdbId: 10378, name: 'Une série'));

    $result = $this->handler->handle(new ImportMovieFromTmdb(10378));

    expect($result->created)->toBeTrue()
        ->and($this->titles->count())->toBe(2);
});

it('gives a different slug to a movie whose slug is already taken', function () {
    $this->titles->save(Title::importMovie(F::titleId(), new TmdbId(999), Slug::fromString('big-buck-bunny-2008'), F::details()));

    $result = $this->handler->handle(new ImportMovieFromTmdb(10378));

    expect($this->titles->find($result->titleId)->slug()->value)->toBe('big-buck-bunny-10378-2008');
});

it('falls back to a neutral slug when the title has no letter or digit', function () {
    $this->metadata->withMovie(77, new TitleDetails(name: '???', releaseYear: 2020));

    $result = $this->handler->handle(new ImportMovieFromTmdb(77));

    expect($this->titles->find($result->titleId)->slug()->value)->toBe('film-77');
});

it('imports nothing when TMDB does not know the movie', function () {
    $this->handler->handle(new ImportMovieFromTmdb(404));
})->throws(MetadataNotFound::class);

it('saves nothing when TMDB is unavailable', function () {
    $this->metadata->unavailable = true;

    try {
        $this->handler->handle(new ImportMovieFromTmdb(10378));
        $this->fail('An exception was expected.');
    } catch (MetadataProviderUnavailable) {
        expect($this->titles->count())->toBe(0)->and($this->titles->saves)->toBe(0);
    }
});

it('refuses an invalid TMDB id without calling TMDB', function (int $id) {
    try {
        $this->handler->handle(new ImportMovieFromTmdb($id));
        $this->fail('An exception was expected.');
    } catch (InvalidArgumentException) {
        expect($this->metadata->calls)->toBe(0);
    }
})->with([0, -1]);

it('dispatches no event on import', function () {
    $this->handler->handle(new ImportMovieFromTmdb(10378));
    $this->handler->handle(new ImportMovieFromTmdb(10378));

    expect($this->events->dispatched)->toBe([]);
});
