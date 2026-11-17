<?php

declare(strict_types=1);

use HomeFlix\Catalog\Domain\Model\Genre;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TmdbId;

describe('TmdbId', function () {
    it('accepts a positive integer', function () {
        expect((new TmdbId(603))->value)->toBe(603);
    });

    it('rejects zero and negative values', function (int $value) {
        new TmdbId($value);
    })->with([0, -5])->throws(InvalidArgumentException::class);

    it('compares by value', function () {
        expect((new TmdbId(1))->equals(new TmdbId(1)))->toBeTrue()
            ->and((new TmdbId(1))->equals(new TmdbId(2)))->toBeFalse();
    });
});

describe('Slug', function () {
    it('builds a slug from a title and a year', function (string $title, ?int $year, string $expected) {
        expect(Slug::fromTitle($title, $year)->value)->toBe($expected);
    })->with([
        ['Amélie Poulain', 2001, 'amelie-poulain-2001'],
        ["L'Étrange Noël de Mr. Jack", 1993, 'l-etrange-noel-de-mr-jack-1993'],
        ['  Big   Buck Bunny  ', null, 'big-buck-bunny'],
        ['WALL·E', 2008, 'wall-e-2008'],
        ['Tôkyô Monogatari', 1953, 'tokyo-monogatari-1953'],
    ]);

    it('cannot build a slug from a title without any letter or digit', function () {
        Slug::fromTitle('!!! ???', 2020);
    })->throws(InvalidArgumentException::class);

    it('never exceeds 120 characters and keeps the year', function () {
        $slug = Slug::fromTitle(str_repeat('abc ', 100), 1999);

        expect(strlen($slug->value))->toBeLessThanOrEqual(120)
            ->and($slug->value)->toEndWith('-1999');
    });

    it('rejects an invalid slug', function (string $invalid) {
        Slug::fromString($invalid);
    })->with(['', 'Has-Capitals', 'trailing-', '-leading', 'double--dash', 'with space', 'accentué'])
        ->throws(InvalidArgumentException::class);
});

describe('Genre', function () {
    it('trims the name', function () {
        expect((new Genre(28, '  Action '))->name)->toBe('Action');
    });

    it('rejects an invalid genre', function (int $id, string $name) {
        new Genre($id, $name);
    })->with([[0, 'Action'], [28, ''], [28, '   '], [28, str_repeat('a', 101)]])
        ->throws(InvalidArgumentException::class);
});

describe('TitleDetails', function () {
    it('trims the name and removes duplicate genres', function () {
        $details = new TitleDetails(
            name: '  Matrix ',
            genres: [new Genre(28, 'Action'), new Genre(878, 'Science-Fiction'), new Genre(28, 'Action bis')],
        );

        expect($details->name)->toBe('Matrix')
            ->and($details->genres)->toHaveCount(2)
            ->and($details->genres[0]->name)->toBe('Action');
    });

    it('rejects invalid data', function (array $arguments) {
        new TitleDetails(...$arguments);
    })->with([
        'empty name' => [['name' => '  ']],
        'name too long' => [['name' => str_repeat('a', 256)]],
        'empty original name' => [['name' => 'A', 'originalName' => ' ']],
        'year before cinema' => [['name' => 'A', 'releaseYear' => 1800]],
        'year too far' => [['name' => 'A', 'releaseYear' => 2101]],
        'zero runtime' => [['name' => 'A', 'runtimeMinutes' => 0]],
        'absurd runtime' => [['name' => 'A', 'runtimeMinutes' => 10001]],
    ])->throws(InvalidArgumentException::class);

    it('accepts optional fields left empty', function () {
        $details = new TitleDetails('A');

        expect($details->releaseYear)->toBeNull()
            ->and($details->runtimeMinutes)->toBeNull()
            ->and($details->genres)->toBe([]);
    });
});
