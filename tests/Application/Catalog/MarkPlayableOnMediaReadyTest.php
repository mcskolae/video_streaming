<?php

declare(strict_types=1);

use HomeFlix\Catalog\Application\Listener\MarkPlayableOnMediaReady;
use HomeFlix\Media\Domain\Event\MediaAssetReady;
use HomeFlix\Media\Domain\Model\PlayableId;
use HomeFlix\Media\Domain\Model\PlayableRef;
use Tests\Support\Catalog\InMemoryTitleRepository;
use Tests\Support\CatalogFixtures as F;
use Tests\Support\MediaFixtures as M;

beforeEach(function () {
    $this->titles = new InMemoryTitleRepository;
    $this->listener = new MarkPlayableOnMediaReady($this->titles);
});

/** L'événement que le contexte Media publie quand un flux est prêt. */
function ready(PlayableRef $target): MediaAssetReady
{
    return new MediaAssetReady(M::assetId(), $target, M::now());
}

it('makes a movie playable when its media is ready', function () {
    $movie = F::movie();
    $this->titles->save($movie);

    $this->listener->handle(ready(PlayableRef::title(PlayableId::fromString($movie->id()->value))));

    expect($this->titles->find($movie->id())->isPlayable())->toBeTrue();
});

it('makes an episode, and so its series, playable', function () {
    $series = F::series();
    $this->titles->save($series);
    $episode = $series->season(1)->episode(2);

    $this->listener->handle(ready(PlayableRef::episode(PlayableId::fromString($episode->id->value))));

    $reloaded = $this->titles->find($series->id());
    expect($reloaded->isPlayable())->toBeTrue()
        ->and($reloaded->season(1)->episode(2)->isPlayable())->toBeTrue()
        ->and($reloaded->season(1)->episode(1)->isPlayable())->toBeFalse();
});

it('makes a title that was a draft publishable, but does not publish it', function () {
    $movie = F::movie();
    $this->titles->save($movie);

    $this->listener->handle(ready(PlayableRef::title(PlayableId::fromString($movie->id()->value))));

    $reloaded = $this->titles->find($movie->id());
    expect($reloaded->isPublished())->toBeFalse();
    $reloaded->publish(F::now());
    expect($reloaded->isPublished())->toBeTrue();
});

it('can receive the same event twice without changing anything', function () {
    $movie = F::movie();
    $this->titles->save($movie);
    $event = ready(PlayableRef::title(PlayableId::fromString($movie->id()->value)));

    $this->listener->handle($event);
    $saves = $this->titles->saves;
    $this->listener->handle($event);

    expect($this->titles->find($movie->id())->isPlayable())->toBeTrue()->and($this->titles->count())->toBe(1)
        ->and($this->titles->saves)->toBe($saves + 1);
});

it('keeps a published movie published', function () {
    $movie = F::movie();
    $movie->markPlayable();
    $movie->publish(F::now());
    $this->titles->save($movie);

    $this->listener->handle(ready(PlayableRef::title(PlayableId::fromString($movie->id()->value))));

    expect($this->titles->find($movie->id())->isPublished())->toBeTrue();
});

it('does nothing when the movie or the episode no longer exists', function (string $kind) {
    $target = $kind === 'title' ? PlayableRef::title(M::playableId()) : PlayableRef::episode(M::playableId());

    $this->listener->handle(ready($target));

    expect($this->titles->count())->toBe(0)->and($this->titles->saves)->toBe(0);
})->with(['title', 'episode']);

it('does not make a series playable from a media pointing at its own identifier as a movie', function () {
    $series = F::series();
    $this->titles->save($series);

    $this->listener->handle(ready(PlayableRef::title(PlayableId::fromString($series->id()->value))));

    expect($this->titles->find($series->id())->isPlayable())->toBeFalse();
});

it('reads the target from the event as plain strings', function () {
    $id = M::playableId();

    $event = ready(PlayableRef::episode($id));

    expect($event->targetKind())->toBe('episode')->and($event->targetId())->toBe($id->value);
});
