<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Library\Application\Port\FavoritesReader;
use HomeFlix\Library\Domain\Model\Favorite;
use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Model\TitleRef;
use HomeFlix\Library\Domain\Repository\FavoriteRepository;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogFixtures as Catalog;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->favorites = app(FavoriteRepository::class);
    $this->reader = app(FavoritesReader::class);
    $this->user = User::factory()->create();
    $this->member = MemberId::fromString($this->user->id);
});

function favorite(MemberId $member, string $titleId, string $at = '2026-11-17 12:00:00'): Favorite
{
    return new Favorite($member, TitleRef::fromString($titleId), new DateTimeImmutable($at));
}

describe('repository', function () {
    it('stores a favorite and finds it', function () {
        $movie = Catalog::publishedMovie();

        $this->favorites->add(favorite($this->member, $movie->id()->value));

        expect($this->favorites->exists($this->member, TitleRef::fromString($movie->id()->value)))->toBeTrue()
            ->and(DB::table('favorites')->count())->toBe(1);
    });

    it('keeps the original date when the same favorite is added again', function () {
        $movie = Catalog::publishedMovie();

        $this->favorites->add(favorite($this->member, $movie->id()->value, '2026-01-01 10:00:00'));
        $this->favorites->add(favorite($this->member, $movie->id()->value, '2026-06-01 10:00:00'));

        expect(DB::table('favorites')->count())->toBe(1)
            ->and(str_starts_with((string) DB::table('favorites')->value('created_at'), '2026-01-01 10:00:00'))->toBeTrue();
    });

    it('removes a favorite and does not complain about one that is not there', function () {
        $movie = Catalog::publishedMovie();
        $this->favorites->add(favorite($this->member, $movie->id()->value));

        $this->favorites->remove($this->member, TitleRef::fromString($movie->id()->value));
        $this->favorites->remove($this->member, TitleRef::fromString($movie->id()->value));

        expect(DB::table('favorites')->count())->toBe(0);
    });

    it('only touches the favorites of the right member', function () {
        $movie = Catalog::publishedMovie();
        $other = MemberId::fromString(User::factory()->create()->id);
        $this->favorites->add(favorite($this->member, $movie->id()->value));
        $this->favorites->add(favorite($other, $movie->id()->value));

        $this->favorites->remove($this->member, TitleRef::fromString($movie->id()->value));

        expect($this->favorites->exists($other, TitleRef::fromString($movie->id()->value)))->toBeTrue()
            ->and($this->favorites->exists($this->member, TitleRef::fromString($movie->id()->value)))->toBeFalse();
    });
});

describe('database constraints', function () {
    it('refuses a favorite for an unknown title or an unknown member', function (bool $unknownMember) {
        $movie = Catalog::publishedMovie();
        $member = $unknownMember ? '01900000-0000-7000-8000-000000000000' : $this->user->id;
        $title = $unknownMember ? $movie->id()->value : '01900000-0000-7000-8000-000000000000';

        expect(fn () => DB::transaction(fn () => DB::table('favorites')->insert(['user_id' => $member, 'title_id' => $title])))
            ->toThrow(QueryException::class, $unknownMember ? 'favorites_user_id_foreign' : 'favorites_title_id_foreign');
    })->with([[true], [false]]);

    it('refuses a second row for the same member and title', function () {
        $movie = Catalog::publishedMovie();
        DB::table('favorites')->insert(['user_id' => $this->user->id, 'title_id' => $movie->id()->value]);

        expect(fn () => DB::transaction(fn () => DB::table('favorites')->insert(['user_id' => $this->user->id, 'title_id' => $movie->id()->value])))
            ->toThrow(QueryException::class, 'favorites_pkey');
    });

    it('deletes the favorites of a deleted account and of a deleted title', function () {
        $movie = Catalog::publishedMovie('A', 1);
        $other = Catalog::publishedMovie('B', 2);
        $this->favorites->add(favorite($this->member, $movie->id()->value));
        $this->favorites->add(favorite($this->member, $other->id()->value));

        DB::table('titles')->where('id', $movie->id()->value)->delete();
        expect(DB::table('favorites')->count())->toBe(1);

        $this->user->delete();
        expect(DB::table('favorites')->count())->toBe(0);
    });
});

describe('reader', function () {
    it('lists the published favorites of a member, the most recent first', function () {
        $old = Catalog::publishedMovie('Ancien', 1);
        $new = Catalog::publishedMovie('Récent', 2);
        $this->favorites->add(favorite($this->member, $old->id()->value, '2026-01-01 10:00:00'));
        $this->favorites->add(favorite($this->member, $new->id()->value, '2026-06-01 10:00:00'));

        $cards = $this->reader->publishedFor($this->member);

        expect(array_map(fn ($c) => $c->name, $cards))->toBe(['Récent', 'Ancien'])
            ->and($cards[0]->slug)->toBe($new->slug()->value)
            ->and($cards[0]->type->value)->toBe('movie')
            ->and($cards[0]->releaseYear)->toBe(2008)
            ->and($cards[0]->posterPath)->toBe('/poster.jpg');
    });

    it('hides a title that is no longer published but keeps the favorite', function () {
        $movie = Catalog::publishedMovie('Retiré', 1);
        $this->favorites->add(favorite($this->member, $movie->id()->value));
        $loaded = app(TitleRepository::class)->find($movie->id());
        $loaded->unpublish(Catalog::now());
        app(TitleRepository::class)->save($loaded);

        expect($this->reader->publishedFor($this->member))->toBe([])
            ->and(DB::table('favorites')->count())->toBe(1);

        // Republié, il revient dans la liste.
        $loaded = app(TitleRepository::class)->find($movie->id());
        $loaded->publish(Catalog::now());
        app(TitleRepository::class)->save($loaded);
        expect($this->reader->publishedFor($this->member))->toHaveCount(1);
    });

    it('never shows the favorites of another member', function () {
        $movie = Catalog::publishedMovie();
        $other = MemberId::fromString(User::factory()->create()->id);
        $this->favorites->add(favorite($other, $movie->id()->value));

        expect($this->reader->publishedFor($this->member))->toBe([])->and($this->reader->publishedFor($other))->toHaveCount(1);
    });

    it('reads a series as well as a movie, in one query', function () {
        $this->favorites->add(favorite($this->member, Catalog::publishedMovie('Film', 1)->id()->value));
        $this->favorites->add(favorite($this->member, Catalog::publishedSeries('Série', 2)->id()->value, '2026-12-01 10:00:00'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $cards = $this->reader->publishedFor($this->member);

        expect(array_map(fn ($c) => $c->type->value, $cards))->toBe(['series', 'movie'])->and(count(DB::getQueryLog()))->toBe(1);
    });

    it('is empty for a member without favorites', function () {
        expect($this->reader->publishedFor($this->member))->toBe([]);
    });
});
