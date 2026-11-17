<?php

declare(strict_types=1);

use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Infrastructure\Tmdb\TmdbMapper;
use Tests\Support\Tmdb\TmdbFixtures as Tmdb;

describe('movie', function () {
    it('maps a complete movie', function () {
        $movie = TmdbMapper::movie(Tmdb::movie());

        expect($movie->tmdbId->value)->toBe(10378)
            ->and($movie->details->name)->toBe('Big Buck Bunny')
            ->and($movie->details->originalName)->toBe('Big Buck Bunny')
            ->and($movie->details->overview)->toBe('Un lapin géant se venge de trois rongeurs.')
            ->and($movie->details->releaseYear)->toBe(2008)
            ->and($movie->details->runtimeMinutes)->toBe(10)
            ->and($movie->details->posterPath)->toBe('/poster.jpg')
            ->and($movie->details->backdropPath)->toBe('/backdrop.jpg')
            ->and(array_map(fn ($g) => [$g->tmdbId, $g->name], $movie->details->genres))->toBe([[16, 'Animation'], [35, 'Comédie']]);
    });

    it('accepts a movie with only an id and a title', function () {
        $movie = TmdbMapper::movie(['id' => 1, 'title' => 'Minimal']);

        expect($movie->details->releaseYear)->toBeNull()
            ->and($movie->details->runtimeMinutes)->toBeNull()
            ->and($movie->details->posterPath)->toBeNull()
            ->and($movie->details->overview)->toBe('')
            ->and($movie->details->genres)->toBe([]);
    });

    it('refuses a response without a usable id or title', function (array $data) {
        TmdbMapper::movie($data);
    })->with([
        'no id' => [['title' => 'A']],
        'zero id' => [['id' => 0, 'title' => 'A']],
        'string id' => [['id' => '12', 'title' => 'A']],
        'no title' => [['id' => 1]],
        'blank title' => [['id' => 1, 'title' => '   ']],
        'title too long' => [['id' => 1, 'title' => str_repeat('a', 256)]],
    ])->throws(InvalidArgumentException::class);

    it('derives the year from the date and ignores implausible dates', function (mixed $date, ?int $expected) {
        expect(TmdbMapper::movie(['id' => 1, 'title' => 'A', 'release_date' => $date])->details->releaseYear)->toBe($expected);
    })->with([
        ['1999-03-31', 1999],
        ['2008', 2008],
        ['', null],
        [null, null],
        ['0000-00-00', null],
        ['1800-01-01', null],
        ['2999-01-01', null],
        ['not a date', null],
        [20080410, null],
    ]);

    it('ignores impossible runtimes', function (mixed $runtime) {
        expect(TmdbMapper::movie(['id' => 1, 'title' => 'A', 'runtime' => $runtime])->details->runtimeMinutes)->toBeNull();
    })->with([[0], [-3], [10001], ['120'], [1.5], [null], [[]]]);

    it('keeps only image paths of the form /name.ext', function (mixed $path, ?string $expected) {
        expect(TmdbMapper::movie(['id' => 1, 'title' => 'A', 'poster_path' => $path])->details->posterPath)->toBe($expected);
    })->with([
        ['/abc-123_x.jpg', '/abc-123_x.jpg'],
        ['//evil.example/x.jpg', null],
        ['http://evil.example/x.jpg', null],
        ['https://evil.example/x.jpg', null],
        ['javascript:alert(1)', null],
        ['/a b.jpg', null],
        ['/../etc/passwd', null],
        ['/a/b.jpg', null],
        ['x.jpg', null],
        ['', null],
        [null, null],
        [['/x.jpg'], null],
        ['/'.str_repeat('a', 201), null],
    ]);

    it('skips genres that are invalid', function () {
        $movie = TmdbMapper::movie(['id' => 1, 'title' => 'A', 'genres' => [
            ['id' => 16, 'name' => 'Animation'], ['id' => 0, 'name' => 'Zéro'], ['id' => 5, 'name' => ''],
            ['name' => 'Sans id'], 'texte', ['id' => 16, 'name' => 'Doublon'], ['id' => 6, 'name' => str_repeat('a', 101)],
        ]]);

        expect(array_map(fn ($g) => $g->name, $movie->details->genres))->toBe(['Animation']);
    });
});

describe('search results', function () {
    it('maps movie results', function () {
        $results = TmdbMapper::searchResults(Tmdb::movieSearch(), TitleType::Movie);

        expect($results)->toHaveCount(2)
            ->and($results[0]->tmdbId->value)->toBe(10378)
            ->and($results[0]->type)->toBe(TitleType::Movie)
            ->and($results[0]->name)->toBe('Big Buck Bunny')
            ->and($results[0]->releaseYear)->toBe(2008)
            ->and($results[0]->posterPath)->toBe('/poster.jpg')
            ->and($results[1]->releaseYear)->toBeNull()
            ->and($results[1]->posterPath)->toBeNull();
    });

    it('reads the series field names', function () {
        $results = TmdbMapper::searchResults(['results' => [
            ['id' => 7, 'name' => 'Une série', 'original_name' => 'A series', 'overview' => 'x', 'first_air_date' => '2015-05-01', 'poster_path' => '/s.jpg'],
        ]], TitleType::Series);

        expect($results[0]->name)->toBe('Une série')
            ->and($results[0]->originalName)->toBe('A series')
            ->and($results[0]->releaseYear)->toBe(2015)
            ->and($results[0]->type)->toBe(TitleType::Series);
    });

    it('skips entries without a usable id or title and keeps going', function () {
        $results = TmdbMapper::searchResults(['results' => [
            ['title' => 'Sans id'], ['id' => 2], ['id' => -1, 'title' => 'Négatif'], 'texte', ['id' => 3, 'title' => 'Bon'],
        ]], TitleType::Movie);

        expect(array_map(fn ($r) => $r->tmdbId->value, $results))->toBe([3]);
    });

    it('keeps at most 20 results', function () {
        $many = array_map(fn (int $i) => ['id' => $i, 'title' => "Film {$i}"], range(1, 35));

        expect(TmdbMapper::searchResults(['results' => $many], TitleType::Movie))->toHaveCount(20);
    });

    it('returns an empty list when there are no results or the payload is odd', function (array $payload) {
        expect(TmdbMapper::searchResults($payload, TitleType::Movie))->toBe([]);
    })->with([[[]], [['results' => []]], [['results' => 'oops']], [['results' => null]]]);
});

describe('series', function () {
    /** @return array<int, array<string, mixed>|null> */
    function allSeasons(): array
    {
        return [0 => Tmdb::season(0), 1 => Tmdb::season(1), 2 => Tmdb::season(2)];
    }

    it('maps a series with its seasons and episodes', function () {
        $series = TmdbMapper::series(Tmdb::series(), allSeasons());

        expect($series->tmdbId->value)->toBe(1399)
            ->and($series->details->name)->toBe('Sintel Chronicles')
            ->and($series->details->releaseYear)->toBe(2010)
            ->and($series->details->runtimeMinutes)->toBe(24)
            ->and(array_map(fn ($s) => $s->number, $series->seasons))->toBe([0, 1, 2])
            ->and($series->seasons[1]->name)->toBe('Saison 1')
            ->and($series->seasons[1]->overview)->toBe('Résumé de la saison 1.')
            ->and($series->seasons[1]->episodes)->toHaveCount(2)
            ->and($series->seasons[1]->episodes[0]->name)->toBe('Pilote')
            ->and($series->seasons[1]->episodes[0]->runtimeMinutes)->toBe(24)
            ->and($series->seasons[1]->episodes[0]->stillPath)->toBe('/still-1.jpg')
            ->and($series->seasons[1]->episodes[1]->runtimeMinutes)->toBeNull()
            ->and($series->seasons[1]->episodes[1]->stillPath)->toBeNull();
    });

    it('lists the season numbers sorted, without duplicates, keeping the specials', function () {
        $data = ['seasons' => [
            ['season_number' => 2], ['season_number' => 0], ['season_number' => 1], ['season_number' => 1],
            ['season_number' => -1], ['season_number' => 1000], ['season_number' => '3'], ['name' => 'x'], 'texte',
        ]];

        expect(TmdbMapper::seasonNumbers($data))->toBe([0, 1, 2]);
    });

    it('keeps a season that TMDB has no detail for, without episodes', function () {
        $series = TmdbMapper::series(Tmdb::series(), [0 => null, 1 => Tmdb::season(1), 2 => null]);

        expect($series->seasons[0]->episodes)->toBe([])
            ->and($series->seasons[0]->name)->toBe('Épisodes spéciaux')
            ->and($series->seasons[1]->episodes)->toHaveCount(2)
            ->and($series->seasons[2]->name)->toBe('Saison 2');
    });

    it('skips invalid and duplicate episodes and sorts the others', function () {
        $season = ['season_number' => 1, 'name' => 'S1', 'episodes' => [
            ['episode_number' => 3, 'name' => 'Trois'],
            ['episode_number' => 1, 'name' => 'Un'],
            ['episode_number' => 1, 'name' => 'Doublon'],
            ['episode_number' => 0, 'name' => 'Zéro'],
            ['episode_number' => -2, 'name' => 'Négatif'],
            ['episode_number' => 10000, 'name' => 'Trop grand'],
            ['name' => 'Sans numéro'],
            'texte',
        ]];

        $series = TmdbMapper::series(['id' => 1, 'name' => 'S', 'seasons' => [['season_number' => 1]]], [1 => $season]);

        expect(array_map(fn ($e) => [$e->number, $e->name], $series->seasons[0]->episodes))->toBe([[1, 'Un'], [3, 'Trois']]);
    });

    it('truncates a very long episode name instead of failing', function () {
        $season = ['episodes' => [['episode_number' => 1, 'name' => str_repeat('é', 400)]]];

        $series = TmdbMapper::series(['id' => 1, 'name' => 'S', 'seasons' => [['season_number' => 1]]], [1 => $season]);

        expect(mb_strlen($series->seasons[0]->episodes[0]->name))->toBe(255);
    });

    it('takes the first episode runtime and ignores an empty list', function (mixed $runtimes, ?int $expected) {
        $series = TmdbMapper::series(['id' => 1, 'name' => 'S', 'episode_run_time' => $runtimes], []);

        expect($series->details->runtimeMinutes)->toBe($expected);
    })->with([[[45, 60], 45], [[], null], [null, null], [[0], null], ['45', null]]);

    it('accepts a series without any season', function () {
        $series = TmdbMapper::series(['id' => 1, 'name' => 'Vide'], []);

        expect($series->seasons)->toBe([]);
    });

    it('refuses a response without a usable id or name', function (array $data) {
        TmdbMapper::series($data, []);
    })->with([
        'no id' => [['name' => 'S']],
        'no name' => [['id' => 1]],
        'blank name' => [['id' => 1, 'name' => ' ']],
    ])->throws(InvalidArgumentException::class);
});
