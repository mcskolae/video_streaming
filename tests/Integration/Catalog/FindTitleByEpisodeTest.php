<?php

declare(strict_types=1);

use HomeFlix\Catalog\Domain\Model\EpisodeId;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogFixtures as F;

uses(RefreshDatabase::class);

it('finds the series that holds an episode, with all its seasons and episodes', function () {
    $repository = app(TitleRepository::class);
    $series = F::series(tmdbId: 1, name: 'Une série');
    $other = F::series(tmdbId: 2, name: 'Une autre');
    $repository->save($series);
    $repository->save($other);

    $found = $repository->findByEpisodeId($series->season(1)->episode(2)->id);

    expect($found?->id()->equals($series->id()))->toBeTrue()
        ->and($found->season(1)->episodes())->toHaveCount(2);
});

it('does not find a series for an unknown episode or for a movie identifier', function () {
    $repository = app(TitleRepository::class);
    $movie = F::movie();
    $repository->save($movie);

    expect($repository->findByEpisodeId(F::episodeId()))->toBeNull()
        ->and($repository->findByEpisodeId(EpisodeId::fromString($movie->id()->value)))->toBeNull();
});

it('persists an episode that was marked playable through its identifier', function () {
    $repository = app(TitleRepository::class);
    $series = F::series();
    $repository->save($series);

    $loaded = $repository->findByEpisodeId($series->season(1)->episode(1)->id);
    $loaded->markEpisodePlayableById($series->season(1)->episode(1)->id);
    $repository->save($loaded);

    expect(DB::table('episodes')->where('is_playable', true)->count())->toBe(1);
});
