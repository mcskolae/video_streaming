<?php

declare(strict_types=1);

use HomeFlix\Catalog\Application\Query\Public\PublicGenre;
use HomeFlix\Catalog\Application\Query\PublicCatalog;
use Tests\Support\Catalog\FakePublicCatalogReader;

beforeEach(function () {
    $this->reader = new FakePublicCatalogReader;
    $this->catalog = new PublicCatalog($this->reader);
});

describe('search term', function () {
    it('is cleaned before it reaches the reader', function (string $typed, string $expected) {
        $this->catalog->search($typed);

        expect($this->reader->calls)->toBe([['search', [$expected, PublicCatalog::MAX_SEARCH_RESULTS]]]);
    })->with([
        'spaces around' => ['  big buck  ', 'big buck'],
        'inner spaces collapsed' => ["big   \t buck\n bunny", 'big buck bunny'],
        'control characters' => ["a\x00b\x07c", 'a b c'],
        'zero-width characters' => ["big\u{200B}buck", 'big buck'],
        'accents kept' => ['Amélie', 'Amélie'],
        'quotes kept as text' => ["l'été", "l'été"],
    ]);

    it('does not search for an empty term', function (string $typed) {
        expect($this->catalog->search($typed))->toBe([])
            ->and($this->reader->calls)->toBe([]);
    })->with([[''], ['   '], ["\n\t"], ["\x00\x00"], ["\u{200B}"]]);

    it('is cut to 100 characters, counting characters and not bytes', function () {
        expect(mb_strlen(PublicCatalog::normalizeTerm(str_repeat('é', 400))))->toBe(100)
            ->and(PublicCatalog::normalizeTerm(str_repeat('a', 400)))->toBe(str_repeat('a', 100));
    });

    it('keeps the number of results under a fixed ceiling', function () {
        $this->catalog->search('film');

        expect($this->reader->calls[0][1][1])->toBe(48);
    });
});

describe('genre pages', function () {
    it('never asks for a page before the first', function (int $page, int $expected) {
        $this->catalog->byGenre(16, $page);

        expect($this->reader->calls)->toBe([['byGenre', [16, $expected, PublicCatalog::PER_PAGE]]]);
    })->with([[1, 1], [0, 1], [-4, 1], [7, 7]]);

    it('does not look up a genre with an invalid identifier', function (int $id) {
        expect($this->catalog->genre($id))->toBeNull()
            ->and($this->reader->calls)->toBe([]);
    })->with([[0], [-1]]);

    it('looks up a valid genre', function () {
        $this->reader->genre = new PublicGenre(16, 'Animation', 3);

        expect($this->catalog->genre(16)?->name)->toBe('Animation');
    });
});

it('asks for a fixed number of latest titles on the home page', function () {
    $this->catalog->latest();

    expect($this->reader->calls)->toBe([['latest', [PublicCatalog::HOME_LATEST]]]);
});

it('keeps the sitemap under the 50 000 addresses of the protocol', function () {
    $this->catalog->sitemap();

    expect($this->reader->calls)->toBe([['sitemap', [PublicCatalog::MAX_SITEMAP_TITLES]]])
        ->and(PublicCatalog::MAX_SITEMAP_TITLES)->toBeLessThan(50_000);
});
