<?php

declare(strict_types=1);

use HomeFlix\Catalog\Application\Exception\MetadataNotFound;
use HomeFlix\Catalog\Application\Exception\MetadataProviderUnavailable;
use HomeFlix\Catalog\Application\Metadata\SearchResult;
use HomeFlix\Catalog\Application\Query\ListAdminTitles;
use HomeFlix\Catalog\Application\Query\PreviewTmdbMovie;
use HomeFlix\Catalog\Application\Query\PreviewTmdbMovieHandler;
use HomeFlix\Catalog\Application\Query\SearchTmdbMovies;
use HomeFlix\Catalog\Application\Query\SearchTmdbMoviesHandler;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use Tests\Support\Catalog\FakeMetadataProvider;
use Tests\Support\Catalog\FakeTitleListReader;
use Tests\Support\CatalogFixtures as F;

function searchResult(int $id, string $name): SearchResult
{
    return new SearchResult(new TmdbId($id), TitleType::Movie, $name, null, '', 2008, null);
}

describe('search', function () {
    it('flags the movies that are already in the catalog', function () {
        $metadata = new FakeMetadataProvider;
        $metadata->searchResults = [searchResult(1, 'Un'), searchResult(2, 'Deux'), searchResult(3, 'Trois')];
        $handler = new SearchTmdbMoviesHandler($metadata, new FakeTitleListReader(importedMovies: [2]));

        $hits = $handler->handle(new SearchTmdbMovies('film'));

        expect(array_map(fn ($hit) => [$hit->result->tmdbId->value, $hit->imported], $hits))
            ->toBe([[1, false], [2, true], [3, false]]);
    });

    it('returns an empty list without results', function () {
        $handler = new SearchTmdbMoviesHandler(new FakeMetadataProvider, new FakeTitleListReader);

        expect($handler->handle(new SearchTmdbMovies('rien')))->toBe([]);
    });

    it('lets a provider failure through', function () {
        $metadata = new FakeMetadataProvider;
        $metadata->unavailable = true;

        (new SearchTmdbMoviesHandler($metadata, new FakeTitleListReader))->handle(new SearchTmdbMovies('x'));
    })->throws(MetadataProviderUnavailable::class);
});

describe('preview', function () {
    it('shows a movie and whether it is already imported', function (array $imported, bool $expected) {
        $metadata = (new FakeMetadataProvider)->withMovie(10378, F::details());
        $handler = new PreviewTmdbMovieHandler($metadata, new FakeTitleListReader(importedMovies: $imported));

        $preview = $handler->handle(new PreviewTmdbMovie(10378));

        expect($preview->movie->details->name)->toBe('Big Buck Bunny')
            ->and($preview->imported)->toBe($expected);
    })->with([[[], false], [[10378], true], [[5], false]]);

    it('fails on an unknown movie', function () {
        (new PreviewTmdbMovieHandler(new FakeMetadataProvider, new FakeTitleListReader))->handle(new PreviewTmdbMovie(5));
    })->throws(MetadataNotFound::class);

    it('refuses an invalid id', function () {
        (new PreviewTmdbMovieHandler(new FakeMetadataProvider, new FakeTitleListReader))->handle(new PreviewTmdbMovie(0));
    })->throws(InvalidArgumentException::class);
});

describe('list query', function () {
    it('keeps the page and the size in a safe range', function (int $page, int $perPage, int $expectedPage, int $expectedPerPage) {
        $query = new ListAdminTitles($page, $perPage);

        expect([$query->page, $query->perPage])->toBe([$expectedPage, $expectedPerPage]);
    })->with([[1, 20, 1, 20], [0, 20, 1, 20], [-5, 0, 1, 1], [3, 5000, 3, 100]]);
});
