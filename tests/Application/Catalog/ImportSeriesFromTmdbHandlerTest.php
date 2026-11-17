<?php

declare(strict_types=1);

use HomeFlix\Catalog\Application\Command\ImportSeriesFromTmdb;
use HomeFlix\Catalog\Application\Command\ImportSeriesFromTmdbHandler;
use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Metadata\EpisodeMetadata;
use HomeFlix\Catalog\Application\Metadata\SeasonMetadata;
use HomeFlix\Catalog\Application\Metadata\SeriesMetadata;
use HomeFlix\Catalog\Domain\Model\TitleStatus;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use Tests\Support\Catalog\FakeMetadataProvider;
use Tests\Support\Catalog\InMemoryTitleRepository;
use Tests\Support\CatalogFixtures as F;
use Tests\Support\SpyEventDispatcher;
use Tests\Support\UuidV7Generator;

function seriesMetadata(string $name = 'Sintel Chronicles', int $seasons = 2, int $episodes = 3, string $episodeName = 'Épisode'): SeriesMetadata
{
    return new SeriesMetadata(
        new TmdbId(1399),
        F::details($name, 2010),
        array_map(
            fn (int $season) => new SeasonMetadata(
                $season,
                "Saison {$season}",
                '',
                array_map(fn (int $n) => new EpisodeMetadata($n, "{$episodeName} {$n}", '', 24, null), range(1, $episodes)),
            ),
            range(1, $seasons),
        ),
    );
}

beforeEach(function () {
    $this->titles = new InMemoryTitleRepository;
    $this->metadata = (new FakeMetadataProvider)->withSeries(seriesMetadata());
    $this->events = new SpyEventDispatcher;
    $this->handler = new ImportSeriesFromTmdbHandler($this->titles, $this->metadata, new UuidV7Generator, $this->events);
});

it('imports a series with every season and episode, as a draft', function () {
    $result = $this->handler->handle(new ImportSeriesFromTmdb(1399));

    $title = $this->titles->find($result->titleId);
    expect($result->created)->toBeTrue()
        ->and($title->type())->toBe(TitleType::Series)
        ->and($title->status())->toBe(TitleStatus::Draft)
        ->and($title->isPlayable())->toBeFalse()
        ->and($title->slug()->value)->toBe('sintel-chronicles-2010')
        ->and($title->seasons())->toHaveCount(2)
        ->and($title->season(2)->episodes())->toHaveCount(3)
        ->and($title->season(1)->episode(3)->name)->toBe('Épisode 3');
});

it('does not duplicate anything when the series is imported twice', function () {
    $first = $this->handler->handle(new ImportSeriesFromTmdb(1399));
    $before = $this->titles->find($first->titleId);
    $seasonId = $before->season(1)->id->value;
    $episodeId = $before->season(1)->episode(1)->id->value;

    $second = $this->handler->handle(new ImportSeriesFromTmdb(1399));

    $after = $this->titles->find($first->titleId);
    expect($second->created)->toBeFalse()
        ->and($second->titleId->equals($first->titleId))->toBeTrue()
        ->and($this->titles->count())->toBe(1)
        ->and($after->seasons())->toHaveCount(2)
        ->and($after->season(1)->episodes())->toHaveCount(3)
        ->and($after->season(1)->id->value)->toBe($seasonId)
        ->and($after->season(1)->episode(1)->id->value)->toBe($episodeId);
});

it('adds the new episodes and renames the known ones on reimport', function () {
    $first = $this->handler->handle(new ImportSeriesFromTmdb(1399));
    $this->metadata->withSeries(seriesMetadata('Sintel Chronicles', seasons: 3, episodes: 4, episodeName: 'Chapitre'));

    $this->handler->handle(new ImportSeriesFromTmdb(1399));

    $title = $this->titles->find($first->titleId);
    expect($title->seasons())->toHaveCount(3)
        ->and($title->season(1)->episodes())->toHaveCount(4)
        ->and($title->season(1)->episode(1)->name)->toBe('Chapitre 1');
});

it('keeps the playable state, the publication and the episodes TMDB no longer lists', function () {
    $first = $this->handler->handle(new ImportSeriesFromTmdb(1399));
    $title = $this->titles->find($first->titleId);
    $title->markEpisodePlayable(1, 3);
    $title->publish(F::now());
    $this->titles->save($title);

    $this->metadata->withSeries(seriesMetadata(seasons: 1, episodes: 2));
    $this->handler->handle(new ImportSeriesFromTmdb(1399));

    $after = $this->titles->find($first->titleId);
    expect($after->status())->toBe(TitleStatus::Published)
        ->and($after->season(1)->episode(3)->isPlayable())->toBeTrue()
        ->and($after->season(2)->episodes())->toHaveCount(3)
        ->and($after->isPlayable())->toBeTrue();
});

it('keeps the playable flag of an episode that is renamed', function () {
    $first = $this->handler->handle(new ImportSeriesFromTmdb(1399));
    $title = $this->titles->find($first->titleId);
    $title->markEpisodePlayable(1, 1);
    $this->titles->save($title);

    $this->metadata->withSeries(seriesMetadata(episodeName: 'Nouveau'));
    $this->handler->handle(new ImportSeriesFromTmdb(1399));

    $episode = $this->titles->find($first->titleId)->season(1)->episode(1);
    expect($episode->name)->toBe('Nouveau 1')->and($episode->isPlayable())->toBeTrue();
});

it('does not take a movie with the same TMDB id for the series', function () {
    $this->titles->save(F::movie(tmdbId: 1399, name: 'Un film'));

    $result = $this->handler->handle(new ImportSeriesFromTmdb(1399));

    expect($result->created)->toBeTrue()->and($this->titles->count())->toBe(2);
});

it('gives another slug to a series whose name is taken', function () {
    $this->titles->save(F::movie(tmdbId: 5, name: 'Sintel Chronicles'));
    $this->titles->save(F::movie(tmdbId: 6, name: 'Autre'));

    $result = $this->handler->handle(new ImportSeriesFromTmdb(1399));

    expect($this->titles->find($result->titleId)->slug()->value)->toStartWith('sintel-chronicles');
});

it('reports a series TMDB does not know', function () {
    $this->handler->handle(new ImportSeriesFromTmdb(42));
})->throws(MetadataNotFound::class);

it('refuses an invalid TMDB id', function () {
    $this->handler->handle(new ImportSeriesFromTmdb(0));
})->throws(InvalidArgumentException::class);
