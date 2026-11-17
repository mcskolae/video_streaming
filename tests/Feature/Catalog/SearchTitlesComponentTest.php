<?php

declare(strict_types=1);

use HomeFlix\Catalog\Application\Query\PublicCatalog;
use HomeFlix\Catalog\UI\Http\Middleware\LimitLivewireUpdates;
use HomeFlix\Catalog\UI\Livewire\SearchTitles;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Support\CatalogFixtures as F;

beforeEach(function () {
    F::publishedMovie('Big Buck Bunny', 1);
    F::publishedMovie('Sintel', 2, year: 2010);
    F::draftMovie('Big secret', 3);
});

it('starts without results', function () {
    Livewire::test(SearchTitles::class)
        ->assertSee('Saisissez un titre')
        ->assertSet('term', '');
});

it('finds titles as the term changes', function () {
    Livewire::test(SearchTitles::class)
        ->set('term', 'Big')
        ->assertSee('Big Buck Bunny')
        ->assertDontSee('Sintel')
        ->set('term', 'sin')
        ->assertSee('Sintel')
        ->assertDontSee('Big Buck Bunny');
});

it('tolerates a typing mistake', function () {
    Livewire::test(SearchTitles::class)
        ->set('term', 'Big Bukc Bunny')
        ->assertSee('Big Buck Bunny');
});

it('never shows a draft', function () {
    Livewire::test(SearchTitles::class)
        ->set('term', 'secret')
        ->assertDontSee('Big secret')
        ->assertSee('Aucun résultat');
});

it('says when nothing matches and keeps the term visible and escaped', function () {
    Livewire::test(SearchTitles::class)
        ->set('term', '<b>zzzz</b>')
        ->assertSee('Aucun résultat')
        ->assertDontSee('<b>zzzz</b>', false)
        ->assertSee('&lt;b&gt;zzzz&lt;/b&gt;', false);
});

it('counts the results', function () {
    Livewire::test(SearchTitles::class)
        ->set('term', 'Big Buck Bunny')
        ->assertSee('1 résultat');
});

it('reads the term from the URL, so a search can be shared', function () {
    Livewire::withQueryParams(['q' => 'Sintel'])
        ->test(SearchTitles::class)
        ->assertSet('term', 'Sintel')
        ->assertSee('Sintel');
});

it('writes the term in the URL', function () {
    Livewire::test(SearchTitles::class)
        ->set('term', 'Sintel')
        ->assertSetStrict('term', 'Sintel')
        ->assertSee('Sintel');
});

it('survives a very long or odd term', function (string $term) {
    Livewire::test(SearchTitles::class)->set('term', $term)->assertOk();
})->with([[str_repeat('é', 10000)], ["\x00\x01\x02"], ["'; DROP TABLE titles; --"], ['%'], ['\\'], ['   ']]);

it('warns when the results were cut', function () {
    foreach (range(1, PublicCatalog::MAX_SEARCH_RESULTS + 5) as $i) {
        F::publishedMovie("Série numéro {$i}", 100 + $i);
    }

    Livewire::test(SearchTitles::class)
        ->set('term', 'Série numéro')
        ->assertSee('précisez votre recherche');
});

it('is embedded in the search page with an accessible label', function () {
    $html = $this->get('/recherche')->assertOk()->getContent();

    expect($html)->toContain('wire:snapshot')
        ->and($html)->toContain('for="search-term"')
        ->and($html)->toContain('role="search"')
        ->and($html)->toContain('aria-live="polite"')
        ->and($html)->toContain('maxlength="100"');
});

describe('rate limiting of Livewire updates', function () {
    beforeEach(fn () => RateLimiter::clear('livewire-update'));

    it('limits the update requests per IP address', function () {
        $uri = Livewire::getUpdateUri();

        foreach (range(1, LimitLivewireUpdates::MAX_PER_MINUTE) as $i) {
            expect($this->postJson($uri, ['components' => []])->status())->not->toBe(429);
        }

        $this->postJson($uri, ['components' => []])->assertStatus(429);
    });
});

it('limits the public pages per IP address', function () {
    RateLimiter::clear('throttle');

    foreach (range(1, 240) as $i) {
        $this->get('/')->assertOk();
    }

    $this->get('/')->assertStatus(429);
});
