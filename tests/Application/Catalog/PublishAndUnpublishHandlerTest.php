<?php

declare(strict_types=1);

use HomeFlix\Catalog\Application\Command\PublishTitle;
use HomeFlix\Catalog\Application\Command\PublishTitleHandler;
use HomeFlix\Catalog\Application\Command\UnpublishTitle;
use HomeFlix\Catalog\Application\Command\UnpublishTitleHandler;
use HomeFlix\Catalog\Application\Exception\TitleNotFound;
use HomeFlix\Catalog\Domain\Event\TitlePublished;
use HomeFlix\Catalog\Domain\Event\TitleUnpublished;
use HomeFlix\Catalog\Domain\Exception\TitleCannotBePublished;
use Tests\Support\Catalog\InMemoryTitleRepository;
use Tests\Support\CatalogFixtures as F;
use Tests\Support\FixedClock;
use Tests\Support\SpyEventDispatcher;

beforeEach(function () {
    $this->titles = new InMemoryTitleRepository;
    $this->events = new SpyEventDispatcher;
    $this->publish = new PublishTitleHandler($this->titles, new FixedClock, $this->events);
    $this->unpublish = new UnpublishTitleHandler($this->titles, new FixedClock, $this->events);

    $this->playable = F::movie(tmdbId: 1, name: 'Prêt');
    $this->playable->markPlayable();
    $this->titles->save($this->playable);

    $this->draft = F::movie(tmdbId: 2, name: 'Sans média');
    $this->titles->save($this->draft);
});

describe('publish', function () {
    it('publishes a title whose media is ready and announces it', function () {
        $this->publish->handle(new PublishTitle($this->playable->id()->value));

        $title = $this->titles->find($this->playable->id());
        $event = $this->events->dispatched[0] ?? null;

        expect($title->isPublished())->toBeTrue()
            ->and($title->publishedAt())->toEqual(F::now())
            ->and($this->events->dispatched)->toHaveCount(1)
            ->and($event)->toBeInstanceOf(TitlePublished::class);
        assert($event instanceof TitlePublished);
        expect($event->titleId->equals($this->playable->id()))->toBeTrue();
    });

    it('refuses a title with no ready media and leaves it untouched', function () {
        $saves = $this->titles->saves;

        try {
            $this->publish->handle(new PublishTitle($this->draft->id()->value));
            $this->fail('An exception was expected.');
        } catch (TitleCannotBePublished) {
            expect($this->titles->find($this->draft->id())->isPublished())->toBeFalse()
                ->and($this->titles->saves)->toBe($saves)
                ->and($this->events->dispatched)->toBe([]);
        }
    });

    it('announces the publication only once', function () {
        $this->publish->handle(new PublishTitle($this->playable->id()->value));
        $this->publish->handle(new PublishTitle($this->playable->id()->value));

        expect($this->events->dispatched)->toHaveCount(1);
    });

    it('fails on an unknown title', function () {
        $this->publish->handle(new PublishTitle(F::titleId()->value));
    })->throws(TitleNotFound::class);

    it('fails on an identifier that is not a UUID', function () {
        $this->publish->handle(new PublishTitle('not-a-uuid'));
    })->throws(InvalidArgumentException::class);
});

describe('unpublish', function () {
    it('sends a published title back to draft and announces it', function () {
        $this->publish->handle(new PublishTitle($this->playable->id()->value));
        $alreadyDispatched = count($this->events->dispatched);

        $this->unpublish->handle(new UnpublishTitle($this->playable->id()->value));

        $title = $this->titles->find($this->playable->id());
        $new = array_slice($this->events->dispatched, $alreadyDispatched);
        expect($title->isPublished())->toBeFalse()
            ->and($title->publishedAt())->toBeNull()
            ->and($new)->toHaveCount(1)
            ->and($new[0] ?? null)->toBeInstanceOf(TitleUnpublished::class);
    });

    it('does nothing for a title that is already a draft', function () {
        $this->unpublish->handle(new UnpublishTitle($this->draft->id()->value));

        expect($this->events->dispatched)->toBe([]);
    });

    it('fails on an unknown title', function () {
        $this->unpublish->handle(new UnpublishTitle(F::titleId()->value));
    })->throws(TitleNotFound::class);
});
