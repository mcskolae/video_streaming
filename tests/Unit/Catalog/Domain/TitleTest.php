<?php

declare(strict_types=1);

use HomeFlix\Catalog\Domain\Event\TitlePublished;
use HomeFlix\Catalog\Domain\Event\TitleUnpublished;
use HomeFlix\Catalog\Domain\Exception\EpisodeAlreadyExists;
use HomeFlix\Catalog\Domain\Exception\EpisodeNotFound;
use HomeFlix\Catalog\Domain\Exception\SeasonAlreadyExists;
use HomeFlix\Catalog\Domain\Exception\SeasonNotFound;
use HomeFlix\Catalog\Domain\Exception\TitleCannotBePublished;
use HomeFlix\Catalog\Domain\Exception\UnsupportedForTitleType;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TitleStatus;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use Tests\Support\CatalogFixtures as F;

describe('importing', function () {
    it('creates a movie as an unplayable draft without events', function () {
        $movie = F::movie();

        expect($movie->type())->toBe(TitleType::Movie)
            ->and($movie->status())->toBe(TitleStatus::Draft)
            ->and($movie->isPublished())->toBeFalse()
            ->and($movie->isPlayable())->toBeFalse()
            ->and($movie->publishedAt())->toBeNull()
            ->and($movie->seasons())->toBe([])
            ->and($movie->pullDomainEvents())->toBe([]);
    });

    it('creates a series as a draft without season', function () {
        $series = Title::importSeries(
            F::titleId(),
            new TmdbId(1),
            Slug::fromString('une-serie-2020'),
            F::details('Une série', 2020),
        );

        expect($series->type())->toBe(TitleType::Series)
            ->and($series->seasons())->toBe([])
            ->and($series->isPlayable())->toBeFalse();
    });

    it('refreshes the details without changing slug or status', function () {
        $movie = F::movie();
        $slug = $movie->slug();

        $movie->refreshDetails(new TitleDetails(name: 'Big Buck Bunny (restauré)', releaseYear: 2008));

        expect($movie->details()->name)->toBe('Big Buck Bunny (restauré)')
            ->and($movie->slug())->toBe($slug)
            ->and($movie->status())->toBe(TitleStatus::Draft);
    });
});

describe('publishing a movie', function () {
    it('refuses to publish a movie whose media is not ready', function () {
        F::movie()->publish(F::now());
    })->throws(TitleCannotBePublished::class);

    it('publishes a playable movie and records the event', function () {
        $movie = F::movie();
        $movie->markPlayable();

        $movie->publish(F::now());

        $events = $movie->pullDomainEvents();
        expect($movie->isPublished())->toBeTrue()
            ->and($movie->publishedAt())->toEqual(F::now())
            ->and($events)->toHaveCount(1);

        $event = $events[0];
        expect($event)->toBeInstanceOf(TitlePublished::class);
        assert($event instanceof TitlePublished);
        expect($event->titleId->equals($movie->id()))->toBeTrue()
            ->and($event->occurredOn())->toEqual(F::now());
    });

    it('does nothing when publishing twice', function () {
        $movie = F::movie();
        $movie->markPlayable();
        $movie->publish(F::now());
        $movie->pullDomainEvents();

        $movie->publish(new DateTimeImmutable('2030-01-01'));

        expect($movie->publishedAt())->toEqual(F::now())
            ->and($movie->pullDomainEvents())->toBe([]);
    });

    it('unpublishes back to draft and records the event', function () {
        $movie = F::movie();
        $movie->markPlayable();
        $movie->publish(F::now());
        $movie->pullDomainEvents();

        $movie->unpublish(F::now());

        $events = $movie->pullDomainEvents();
        expect($movie->status())->toBe(TitleStatus::Draft)
            ->and($movie->publishedAt())->toBeNull()
            ->and($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(TitleUnpublished::class);
    });

    it('does nothing when unpublishing a draft', function () {
        $movie = F::movie();

        $movie->unpublish(F::now());

        expect($movie->pullDomainEvents())->toBe([]);
    });

    it('goes back to draft automatically when its media is no longer ready', function () {
        $movie = F::movie();
        $movie->markPlayable();
        $movie->publish(F::now());
        $movie->pullDomainEvents();

        $movie->markUnplayable(F::now());

        $events = $movie->pullDomainEvents();
        expect($movie->isPublished())->toBeFalse()
            ->and($movie->isPlayable())->toBeFalse()
            ->and($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(TitleUnpublished::class);
    });

    it('stays a draft without event when an unplayable draft loses its media', function () {
        $movie = F::movie();
        $movie->markPlayable();

        $movie->markUnplayable(F::now());

        expect($movie->pullDomainEvents())->toBe([]);
    });
});

describe('seasons and episodes', function () {
    it('adds seasons and episodes, sorted by number', function () {
        $series = F::series();
        $series->addSeason(F::seasonId(), 0, 'Épisodes spéciaux');
        $series->addEpisode(1, F::episodeId(), 3, 'Final');

        expect(array_map(fn ($s) => $s->number, $series->seasons()))->toBe([0, 1])
            ->and(array_map(fn ($e) => $e->number, $series->season(1)->episodes()))->toBe([1, 2, 3]);
    });

    it('refuses a duplicate season number', function () {
        $series = F::series();

        $series->addSeason(F::seasonId(), 1);
    })->throws(SeasonAlreadyExists::class);

    it('refuses a duplicate episode number in the same season', function () {
        $series = F::series();

        $series->addEpisode(1, F::episodeId(), 2, 'Doublon');
    })->throws(EpisodeAlreadyExists::class);

    it('accepts the same episode number in another season', function () {
        $series = F::series();
        $series->addSeason(F::seasonId(), 2);

        $series->addEpisode(2, F::episodeId(), 1, 'Nouvelle saison');

        expect($series->season(2)->episode(1)->name)->toBe('Nouvelle saison');
    });

    it('fails on an unknown season or episode', function (Closure $action, string $exception) {
        expect($action)->toThrow($exception);
    })->with([
        'unknown season' => [fn () => F::series()->season(9), SeasonNotFound::class],
        'episode in unknown season' => [fn () => F::series()->addEpisode(9, F::episodeId(), 1, 'x'), SeasonNotFound::class],
        'unknown episode' => [fn () => F::series()->season(1)->episode(9), EpisodeNotFound::class],
    ]);

    it('refuses seasons and episodes on a movie', function (Closure $action) {
        expect($action)->toThrow(UnsupportedForTitleType::class);
    })->with([
        'season' => [fn () => F::movie()->addSeason(F::seasonId(), 1)],
        'episode' => [fn () => F::movie()->addEpisode(1, F::episodeId(), 1, 'x')],
    ]);

    it('rejects invalid season and episode numbers', function (Closure $action) {
        expect($action)->toThrow(InvalidArgumentException::class);
    })->with([
        'negative season' => [fn () => F::series()->addSeason(F::seasonId(), -1)],
        'season too high' => [fn () => F::series()->addSeason(F::seasonId(), 1000)],
        'episode zero' => [fn () => F::series()->addEpisode(1, F::episodeId(), 0, 'x')],
        'episode runtime zero' => [fn () => F::series()->addEpisode(1, F::episodeId(), 9, 'x', runtimeMinutes: 0)],
    ]);
});

describe('publishing a series', function () {
    it('refuses to publish a series without any season', function () {
        $series = Title::importSeries(
            F::titleId(),
            new TmdbId(2),
            Slug::fromString('vide-2020'),
            F::details('Vide', 2020),
        );

        $series->publish(F::now());
    })->throws(TitleCannotBePublished::class);

    it('refuses to publish a series whose episodes are not ready', function () {
        F::series()->publish(F::now());
    })->throws(TitleCannotBePublished::class);

    it('becomes playable and publishable as soon as one episode is ready', function () {
        $series = F::series();

        $series->markEpisodePlayable(1, 2);
        $series->publish(F::now());

        expect($series->isPlayable())->toBeTrue()
            ->and($series->isPublished())->toBeTrue()
            ->and($series->season(1)->episode(1)->isPlayable())->toBeFalse()
            ->and($series->season(1)->episode(2)->isPlayable())->toBeTrue();
    });

    it('stays published while at least one episode is ready', function () {
        $series = F::series();
        $series->markEpisodePlayable(1, 1);
        $series->markEpisodePlayable(1, 2);
        $series->publish(F::now());
        $series->pullDomainEvents();

        $series->markEpisodeUnplayable(1, 1, F::now());

        expect($series->isPublished())->toBeTrue()
            ->and($series->pullDomainEvents())->toBe([]);
    });

    it('goes back to draft when its last ready episode is lost', function () {
        $series = F::series();
        $series->markEpisodePlayable(1, 1);
        $series->publish(F::now());
        $series->pullDomainEvents();

        $series->markEpisodeUnplayable(1, 1, F::now());

        expect($series->isPublished())->toBeFalse()
            ->and($series->pullDomainEvents()[0])->toBeInstanceOf(TitleUnpublished::class);
    });

    it('has no movie-style playable flag', function (Closure $action) {
        expect($action)->toThrow(UnsupportedForTitleType::class);
    })->with([
        'mark playable' => [fn () => F::series()->markPlayable()],
        'mark unplayable' => [fn () => F::series()->markUnplayable(F::now())],
    ]);

    it('has no episode-level playable flag on a movie', function (Closure $action) {
        expect($action)->toThrow(UnsupportedForTitleType::class);
    })->with([
        'mark episode playable' => [fn () => F::movie()->markEpisodePlayable(1, 1)],
        'mark episode unplayable' => [fn () => F::movie()->markEpisodeUnplayable(1, 1, F::now())],
    ]);
});
