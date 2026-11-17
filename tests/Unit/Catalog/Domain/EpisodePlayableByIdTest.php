<?php

declare(strict_types=1);

use HomeFlix\Catalog\Domain\Exception\EpisodeNotFound;
use HomeFlix\Catalog\Domain\Exception\UnsupportedForTitleType;
use Tests\Support\CatalogFixtures as F;

it('marks the episode with this identifier as playable, and only that one', function () {
    $series = F::series();
    $second = $series->season(1)->episode(2);

    $series->markEpisodePlayableById($second->id);

    expect($second->isPlayable())->toBeTrue()
        ->and($series->season(1)->episode(1)->isPlayable())->toBeFalse()
        ->and($series->isPlayable())->toBeTrue();
});

it('finds the episode in any season', function () {
    $series = F::series();
    $series->addSeason(F::seasonId(), 2);
    $episode = $series->addEpisode(2, F::episodeId(), 1, 'Saison 2');

    $series->markEpisodePlayableById($episode->id);

    expect($series->season(2)->episode(1)->isPlayable())->toBeTrue()
        ->and($series->season(1)->episode(1)->isPlayable())->toBeFalse();
});

it('lets a series be published once an episode is playable', function () {
    $series = F::series();
    $series->markEpisodePlayableById($series->season(1)->episode(1)->id);

    $series->publish(F::now());

    expect($series->isPublished())->toBeTrue();
});

it('fails for an episode that is not in the series', function () {
    F::series()->markEpisodePlayableById(F::episodeId());
})->throws(EpisodeNotFound::class);

it('refuses to be used on a movie', function () {
    F::movie()->markEpisodePlayableById(F::episodeId());
})->throws(UnsupportedForTitleType::class);
