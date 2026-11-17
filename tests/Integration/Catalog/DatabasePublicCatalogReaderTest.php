<?php

declare(strict_types=1);

use HomeFlix\Catalog\Application\Port\PublicCatalogReader;
use HomeFlix\Catalog\Application\Query\Public\PublicTitleCard;
use HomeFlix\Catalog\Domain\Model\Genre;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogFixtures as F;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->reader = app(PublicCatalogReader::class);
});

/**
 * @param  list<PublicTitleCard>  $cards
 * @return list<string>
 */
function names(array $cards): array
{
    return array_map(fn ($card) => $card->name, $cards);
}

describe('only published titles are visible', function () {
    it('hides drafts everywhere', function () {
        F::publishedMovie('Visible', 1);
        F::draftMovie('Caché', 2);

        expect(names($this->reader->latest(10)))->toBe(['Visible'])
            ->and(names($this->reader->byGenre(16, 1, 10)->cards))->toBe(['Visible'])
            ->and($this->reader->byGenre(16, 1, 10)->total)->toBe(1)
            ->and(names($this->reader->search('Caché', 10)))->toBe([])
            ->and($this->reader->bySlug(F::draftMovie('Autre', 3)->slug()->value))->toBeNull();
    });

    it('hides a title that was unpublished', function () {
        $title = F::publishedMovie('Retiré', 1);
        $title->unpublish(F::now());
        app(TitleRepository::class)->save($title);

        expect($this->reader->latest(10))->toBe([])
            ->and($this->reader->genres())->toBe([])
            ->and($this->reader->bySlug($title->slug()->value))->toBeNull();
    });

    it('does not count a draft in the genre totals and hides a genre with no published title', function () {
        F::publishedMovie('Publié', 1, [new Genre(16, 'Animation')]);
        F::draftMovie('Brouillon', 2); // genres Animation et Comédie, mais en brouillon

        $genres = $this->reader->genres();

        expect(array_map(fn ($g) => [$g->name, $g->titleCount], $genres))->toBe([['Animation', 1]])
            ->and($this->reader->genre(35))->toBeNull()
            ->and($this->reader->genre(16)?->titleCount)->toBe(1);
    });
});

describe('latest', function () {
    it('lists the most recently published first and respects the limit', function () {
        F::publishedMovie('Ancien', 1, publishedAt: '2026-01-01 10:00:00');
        F::publishedMovie('Récent', 2, publishedAt: '2026-03-01 10:00:00');
        F::publishedMovie('Milieu', 3, publishedAt: '2026-02-01 10:00:00');

        expect(names($this->reader->latest(10)))->toBe(['Récent', 'Milieu', 'Ancien'])
            ->and(names($this->reader->latest(2)))->toBe(['Récent', 'Milieu']);
    });

    it('maps the card', function () {
        F::publishedMovie('Big Buck Bunny', 10378);

        $card = $this->reader->latest(1)[0];

        expect($card->slug)->toBe('big-buck-bunny-2008')
            ->and($card->type)->toBe(TitleType::Movie)
            ->and($card->releaseYear)->toBe(2008)
            ->and($card->posterPath)->toBe('/poster.jpg');
    });
});

describe('genres', function () {
    it('lists the genres alphabetically with their number of published titles', function () {
        F::publishedMovie('Un', 1, [new Genre(35, 'Comédie'), new Genre(16, 'Animation')]);
        F::publishedMovie('Deux', 2, [new Genre(16, 'Animation')]);

        expect(array_map(fn ($g) => [$g->tmdbId, $g->name, $g->titleCount], $this->reader->genres()))
            ->toBe([[16, 'Animation', 2], [35, 'Comédie', 1]]);
    });

    it('paginates the titles of a genre, newest first', function () {
        foreach (range(1, 25) as $i) {
            F::publishedMovie("Film {$i}", $i, [new Genre(16, 'Animation')], sprintf('2026-01-%02d 10:00:00', $i));
        }
        F::publishedMovie('Autre genre', 99, [new Genre(35, 'Comédie')]);

        $first = $this->reader->byGenre(16, 1, 20);
        $second = $this->reader->byGenre(16, 2, 20);

        expect($first->cards)->toHaveCount(20)
            ->and($second->cards)->toHaveCount(5)
            ->and($first->total)->toBe(25)
            ->and(names($first->cards)[0])->toBe('Film 25')
            ->and(names($second->cards))->not->toContain('Autre genre')
            ->and($this->reader->byGenre(16, 9, 20)->cards)->toBe([]);
    });

    it('returns nothing for an unknown genre', function () {
        F::publishedMovie();

        expect($this->reader->genre(999))->toBeNull()
            ->and($this->reader->byGenre(999, 1, 20)->total)->toBe(0);
    });
});

describe('search', function () {
    beforeEach(function () {
        F::publishedMovie('Big Buck Bunny', 1);
        F::publishedMovie('Sintel', 2, year: 2010);
        F::publishedMovie('Amélie Poulain', 3, year: 2001);
        F::publishedMovie('Le Roi Lion', 4, year: 1994);
    });

    it('finds a title from its exact name', function () {
        expect(names($this->reader->search('Big Buck Bunny', 10)))->toBe(['Big Buck Bunny']);
    });

    it('tolerates one typing mistake', function (string $typed) {
        expect(names($this->reader->search($typed, 10)))->toContain('Big Buck Bunny');
    })->with([
        'transposed letters' => ['Big Bukc Bunny'],
        'missing letter' => ['Big Buk Bunny'],
        'extra letter' => ['Bigg Buck Bunny'],
        'wrong letter' => ['Big Bock Bunny'],
        'lowercase' => ['big buck bunny'],
    ]);

    it('ignores accents in both directions', function (string $typed) {
        expect(names($this->reader->search($typed, 10)))->toContain('Amélie Poulain');
    })->with([['amelie'], ['AMELIE'], ['Amélie'], ['amelie poulain'], ['poulain']]);

    it('finds a title from a few letters', function (string $typed, string $expected) {
        expect(names($this->reader->search($typed, 10)))->toContain($expected);
    })->with([['sin', 'Sintel'], ['Ro', 'Le Roi Lion'], ['lion', 'Le Roi Lion']]);

    it('finds nothing unrelated', function () {
        expect($this->reader->search('zzzzzz', 10))->toBe([]);
    });

    it('puts the closest match first', function () {
        F::publishedMovie('Sintel Chronicles', 5, year: 2012);

        expect(names($this->reader->search('Sintel', 10))[0])->toBe('Sintel');
    });

    it('respects the limit', function () {
        foreach (range(1, 10) as $i) {
            F::publishedMovie("Film numéro {$i}", 100 + $i);
        }

        expect($this->reader->search('Film numéro', 4))->toHaveCount(4);
    });

    it('treats the term as text, not as a pattern or as SQL', function (string $term) {
        expect($this->reader->search($term, 10))->toBe([]);
        expect(DB::table('titles')->count())->toBe(4);
    })->with([
        'percent' => ['%'],
        'underscore' => ['B_g'],
        'wildcards' => ['%%%'],
        'backslash' => ['\\'],
        'quote' => ["'"],
        'injection' => ["'; DROP TABLE titles; --"],
        'union' => ["x' UNION SELECT id FROM users --"],
    ]);

    it('still finds a title whose name contains a special character', function () {
        F::publishedMovie('100% Loup', 50, year: 2020);

        expect(names($this->reader->search('100%', 10)))->toBe(['100% Loup']);
    });

    it('finds nothing for a term without any letter or digit', function (string $term) {
        expect($this->reader->search($term, 10))->toBe([]);
    })->with([['???'], ['...'], ['---']]);
});

describe('title page', function () {
    it('returns the details of a published movie, with sorted genres and no seasons', function () {
        $movie = F::publishedMovie('Big Buck Bunny', 10378);

        $details = $this->reader->bySlug($movie->slug()->value);

        expect($details->name)->toBe('Big Buck Bunny')
            ->and($details->type)->toBe(TitleType::Movie)
            ->and($details->overview)->toBe('Synopsis de Big Buck Bunny.')
            ->and($details->releaseYear)->toBe(2008)
            ->and($details->runtimeMinutes)->toBe(10)
            ->and($details->posterPath)->toBe('/poster.jpg')
            ->and($details->backdropPath)->toBe('/backdrop.jpg')
            ->and(array_map(fn ($g) => $g->name, $details->genres))->toBe(['Animation', 'Comédie'])
            ->and($details->seasons)->toBe([]);
    });

    it('returns a series with its seasons and episodes in order, the specials first', function () {
        $series = F::publishedSeries('Sintel Chronicles', 1399, seasons: 2, episodes: 3);
        $series->addSeason(F::seasonId(), 0, 'Spéciaux');
        $series->addEpisode(0, F::episodeId(), 1, 'Bonus');
        app(TitleRepository::class)->save($series);

        $details = $this->reader->bySlug($series->slug()->value);

        expect(array_map(fn ($s) => $s->number, $details->seasons))->toBe([0, 1, 2])
            ->and(array_map(fn ($e) => $e->number, $details->seasons[2]->episodes))->toBe([1, 2, 3])
            ->and($details->seasons[0]->episodes[0]->name)->toBe('Bonus')
            ->and($details->seasons[1]->episodes[0]->playable)->toBeTrue()
            ->and($details->seasons[1]->episodes[1]->playable)->toBeFalse()
            ->and($details->seasons[1]->name)->toBe('Saison 1')
            ->and($details->seasons[1]->episodes[0]->runtimeMinutes)->toBe(24);
    });

    it('keeps a season that has no episode', function () {
        $series = F::publishedSeries('Sintel Chronicles', 1399, seasons: 1, episodes: 1);
        $series->addSeason(F::seasonId(), 2, 'Vide');
        app(TitleRepository::class)->save($series);

        $details = $this->reader->bySlug($series->slug()->value);

        expect($details->seasons)->toHaveCount(2)
            ->and($details->seasons[1]->episodes)->toBe([]);
    });

    it('returns null for an unknown slug', function () {
        expect($this->reader->bySlug('inconnu-2020'))->toBeNull();
    });

    it('reads a movie in at most 2 queries and a series in at most 3, whatever its size', function () {
        $movie = F::publishedMovie('Film', 1);
        $small = F::publishedSeries('Petite', 2, seasons: 1, episodes: 1);
        $big = F::publishedSeries('Grande', 3, seasons: 10, episodes: 12);

        $count = function (string $slug): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->reader->bySlug($slug);

            return count(DB::getQueryLog());
        };

        expect($count($movie->slug()->value))->toBeLessThanOrEqual(2)
            ->and($count($small->slug()->value))->toBeLessThanOrEqual(3)
            ->and($count($big->slug()->value))->toBe($count($small->slug()->value));
    });
});

describe('list queries do not grow with the number of titles', function () {
    it('uses the same number of queries for 3 and for 40 titles', function () {
        $measure = function (): array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->reader->latest(12);
            $this->reader->genres();
            $this->reader->byGenre(16, 1, 24);
            $this->reader->search('Film', 48);

            return [count(DB::getQueryLog())];
        };

        foreach (range(1, 3) as $i) {
            F::publishedMovie("Film {$i}", $i);
        }
        $few = $measure();

        foreach (range(4, 40) as $i) {
            F::publishedMovie("Film {$i}", $i);
        }
        $many = $measure();

        expect($many)->toBe($few)->and($many[0])->toBeLessThanOrEqual(5);
    });
});
