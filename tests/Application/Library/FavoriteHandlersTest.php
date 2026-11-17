<?php

declare(strict_types=1);

use HomeFlix\Library\Application\Command\AddFavorite;
use HomeFlix\Library\Application\Command\AddFavoriteHandler;
use HomeFlix\Library\Application\Command\RemoveFavorite;
use HomeFlix\Library\Application\Command\RemoveFavoriteHandler;
use HomeFlix\Library\Application\Exception\TitleNotAvailable;
use HomeFlix\Library\Application\Query\IsFavoriteHandler;
use Ramsey\Uuid\Uuid;
use Tests\Support\FixedClock;
use Tests\Support\Library\FakeTitleDirectory;
use Tests\Support\Library\InMemoryFavoriteRepository;

beforeEach(function () {
    $this->favorites = new InMemoryFavoriteRepository;
    $this->titles = new FakeTitleDirectory;
    $this->member = Uuid::uuid7()->toString();
    $this->title = Uuid::uuid7()->toString();
    $this->titles->published = [$this->title];

    $this->add = new AddFavoriteHandler($this->favorites, $this->titles, new FixedClock);
    $this->remove = new RemoveFavoriteHandler($this->favorites);
    $this->isFavorite = new IsFavoriteHandler($this->favorites);
});

it('adds a published title to the favorites of a member', function () {
    $this->add->handle(new AddFavorite($this->member, $this->title));

    expect($this->isFavorite->handle($this->member, $this->title))->toBeTrue()
        ->and(array_values($this->favorites->stored)[0]->markedAt)->toEqual(new DateTimeImmutable('2026-11-17 12:00:00'));
});

it('adds the same favorite twice without any effect', function () {
    $this->add->handle(new AddFavorite($this->member, $this->title));
    $this->add->handle(new AddFavorite($this->member, $this->title));
    $this->add->handle(new AddFavorite($this->member, $this->title));

    expect($this->favorites->stored)->toHaveCount(1)->and($this->favorites->writes)->toBe(1);
});

it('keeps the favorites of different members apart', function () {
    $other = Uuid::uuid7()->toString();

    $this->add->handle(new AddFavorite($this->member, $this->title));

    expect($this->isFavorite->handle($other, $this->title))->toBeFalse();
});

it('refuses a title that is not published, and one that does not exist', function (string $title) {
    $this->add->handle(new AddFavorite($this->member, $title));
})->with(fn () => [[Uuid::uuid7()->toString()]])->throws(TitleNotAvailable::class);

it('adds nothing when the title is refused', function () {
    try {
        $this->add->handle(new AddFavorite($this->member, Uuid::uuid7()->toString()));
    } catch (TitleNotAvailable) {
    }

    expect($this->favorites->stored)->toBe([]);
});

it('refuses identifiers that are not UUIDs', function (string $member, string $title) {
    $this->add->handle(new AddFavorite($member, $title));
})->with([['not-a-uuid', '01900000-0000-7000-8000-000000000000'], ['01900000-0000-7000-8000-000000000000', '../../etc'], ['', '']])->throws(InvalidArgumentException::class);

it('removes a favorite', function () {
    $this->add->handle(new AddFavorite($this->member, $this->title));

    $this->remove->handle(new RemoveFavorite($this->member, $this->title));

    expect($this->isFavorite->handle($this->member, $this->title))->toBeFalse();
});

it('removes a favorite even when its title is no longer published', function () {
    $this->add->handle(new AddFavorite($this->member, $this->title));
    $this->titles->published = [];

    $this->remove->handle(new RemoveFavorite($this->member, $this->title));

    expect($this->favorites->stored)->toBe([]);
});

it('does nothing when removing something that is not a favorite', function () {
    $this->remove->handle(new RemoveFavorite($this->member, $this->title));

    expect($this->favorites->stored)->toBe([]);
});

it('removes only the favorite of the right member', function () {
    $other = Uuid::uuid7()->toString();
    $this->add->handle(new AddFavorite($this->member, $this->title));
    $this->add->handle(new AddFavorite($other, $this->title));

    $this->remove->handle(new RemoveFavorite($this->member, $this->title));

    expect($this->isFavorite->handle($other, $this->title))->toBeTrue();
});
