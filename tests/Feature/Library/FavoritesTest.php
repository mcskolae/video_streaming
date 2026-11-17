<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Library\UI\Livewire\FavoriteButton;
use Livewire\Livewire;
use Tests\Support\CatalogFixtures as Catalog;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->movie = Catalog::publishedMovie('Big Buck Bunny', 1);
});

describe('the button on the title page', function () {
    it('is not shown to a visitor', function () {
        $this->get(route('titles.show', $this->movie->slug()->value))->assertOk()
            ->assertDontSee('Ajouter aux favoris')->assertDontSee('favorite-button', false);
    });

    it('is shown to a member', function () {
        $this->actingAs($this->user)->get(route('titles.show', $this->movie->slug()->value))->assertOk()
            ->assertSee('Ajouter aux favoris')->assertSee('aria-pressed="false"', false);
    });

    it('shows a title that is already a favorite as such', function () {
        DB::table('favorites')->insert(['user_id' => $this->user->id, 'title_id' => $this->movie->id()->value]);

        $this->actingAs($this->user)->get(route('titles.show', $this->movie->slug()->value))
            ->assertSee('Retirer des favoris')->assertSee('aria-pressed="true"', false);
    });
});

describe('the component', function () {
    it('adds then removes a favorite', function () {
        $component = Livewire::actingAs($this->user)->test(FavoriteButton::class, ['titleId' => $this->movie->id()->value])
            ->assertSet('isFavorite', false)
            ->call('toggle')
            ->assertSet('isFavorite', true)
            ->assertSee('Retirer des favoris');

        expect(DB::table('favorites')->where('user_id', $this->user->id)->count())->toBe(1);

        $component->call('toggle')->assertSet('isFavorite', false)->assertSee('Ajouter aux favoris');
        expect(DB::table('favorites')->count())->toBe(0);
    });

    it('is idempotent when the page is stale and the favorite already exists', function () {
        $component = Livewire::actingAs($this->user)->test(FavoriteButton::class, ['titleId' => $this->movie->id()->value]);
        DB::table('favorites')->insert(['user_id' => $this->user->id, 'title_id' => $this->movie->id()->value]);

        $component->call('toggle')->assertSet('isFavorite', true);

        expect(DB::table('favorites')->count())->toBe(1);
    });

    it('refuses a title that is not published', function () {
        $draft = Catalog::draftMovie('Brouillon', 2);

        Livewire::actingAs($this->user)->test(FavoriteButton::class, ['titleId' => $draft->id()->value])
            ->call('toggle')
            ->assertSet('isFavorite', false)
            ->assertSee('Ce titre n\'est plus disponible.');

        expect(DB::table('favorites')->count())->toBe(0);
    });

    it('does not crash on an identifier that is not a UUID', function () {
        Livewire::actingAs($this->user)->test(FavoriteButton::class, ['titleId' => '../../etc'])
            ->call('toggle')->assertOk()->assertSee('Ce titre n\'est plus disponible.');

        expect(DB::table('favorites')->count())->toBe(0);
    });

    it('refuses a visitor', function () {
        Livewire::test(FavoriteButton::class, ['titleId' => $this->movie->id()->value])->call('toggle')->assertForbidden();

        expect(DB::table('favorites')->count())->toBe(0);
    });

    it('always acts for the member of the session, never for one named by the request', function () {
        $victim = User::factory()->create();
        $component = Livewire::actingAs($this->user)->test(FavoriteButton::class, ['titleId' => $this->movie->id()->value]);

        $component->call('toggle');

        expect(DB::table('favorites')->where('user_id', $victim->id)->count())->toBe(0)
            ->and(DB::table('favorites')->where('user_id', $this->user->id)->count())->toBe(1)
            ->and(array_keys((new ReflectionClass(FavoriteButton::class))->getDefaultProperties()))->not->toContain('memberId', 'userId');
    });

    it('cannot be used by a visitor after the session ended', function () {
        $component = Livewire::actingAs($this->user)->test(FavoriteButton::class, ['titleId' => $this->movie->id()->value]);
        auth()->logout();

        $component->call('toggle')->assertForbidden();

        expect(DB::table('favorites')->count())->toBe(0);
    });
});

describe('the favorites page', function () {
    it('sends a visitor to the login page', function () {
        $this->get('/favoris')->assertRedirect('/login');
    });

    it('says when there is no favorite', function () {
        $this->actingAs($this->user)->get('/favoris')->assertOk()->assertSee('Vous n\'avez pas encore de favori');
    });

    it('lists the favorites with their poster and a link, the most recent first', function () {
        $second = Catalog::publishedMovie('Sintel', 2);
        DB::table('favorites')->insert([
            ['user_id' => $this->user->id, 'title_id' => $this->movie->id()->value, 'created_at' => '2026-01-01 10:00:00+00'],
            ['user_id' => $this->user->id, 'title_id' => $second->id()->value, 'created_at' => '2026-06-01 10:00:00+00'],
        ]);

        $html = $this->actingAs($this->user)->get('/favoris')->assertOk()
            ->assertSee('2 favoris')
            ->assertSee(route('titles.show', $this->movie->slug()->value), false)
            ->assertSee('https://image.tmdb.org/t/p/w342/poster.jpg', false)
            ->getContent();

        expect(strpos($html, 'Sintel'))->toBeLessThan(strpos($html, 'Big Buck Bunny'));
    });

    it('hides a title that is no longer published, and keeps it for when it comes back', function () {
        DB::table('favorites')->insert(['user_id' => $this->user->id, 'title_id' => $this->movie->id()->value]);
        $loaded = app(TitleRepository::class)->find($this->movie->id());
        $loaded->unpublish(Catalog::now());
        app(TitleRepository::class)->save($loaded);

        $this->actingAs($this->user)->get('/favoris')->assertOk()->assertDontSee('Big Buck Bunny')->assertSee('Vous n\'avez pas encore de favori');
        expect(DB::table('favorites')->count())->toBe(1);
    });

    it('never shows the favorites of another member', function () {
        $other = User::factory()->create();
        DB::table('favorites')->insert(['user_id' => $other->id, 'title_id' => $this->movie->id()->value]);

        $this->actingAs($this->user)->get('/favoris')->assertOk()->assertDontSee('Big Buck Bunny');
    });

    it('escapes what the catalog sent', function () {
        $xss = Catalog::publishedMovie('<script>alert(1)</script>', 9);
        DB::table('favorites')->insert(['user_id' => $this->user->id, 'title_id' => $xss->id()->value]);

        $this->actingAs($this->user)->get('/favoris')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    });

    it('is linked from the menu of a member and not of a visitor', function () {
        $this->get('/')->assertDontSee(route('favorites.index'), false);
        $this->actingAs($this->user)->get('/')->assertSee(route('favorites.index'), false);
    });

    it('is protected by authentication', function () {
        $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'favoris');

        expect($route->gatherMiddleware())->toContain('auth', 'web');
    });
});
