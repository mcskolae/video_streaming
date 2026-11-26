<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Catalog\Domain\Model\Genre;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use Tests\Support\CatalogFixtures as F;

/** Charge le HTML d'une réponse pour l'examiner comme un navigateur. */
function dom(string $html): DOMDocument
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    libxml_clear_errors();

    return $document;
}

function count_of(DOMDocument $dom, string $tag): int
{
    return $dom->getElementsByTagName($tag)->length;
}

/** @return array<string, string> */
function publicPages(): array
{
    return [
        'home' => '/',
        'search' => '/recherche?q=big',
        'movie' => '/titres/big-buck-bunny-2008',
        'series' => '/titres/sintel-chronicles-2010',
    ];
}

describe('home', function () {
    it('is public and shows an empty state on an empty catalog', function () {
        $this->get('/')->assertOk()->assertSee('Le catalogue est vide pour le moment');
    });

    it('shows the published titles and a section per genre, never the drafts', function () {
        F::publishedMovie('Big Buck Bunny', 1);
        F::draftMovie('Film secret', 2);

        $this->get('/')
            ->assertOk()
            ->assertSee('Big Buck Bunny')
            ->assertDontSee('Film secret')
            ->assertSee('id="genre-16"', false)
            ->assertSee(route('titles.show', 'big-buck-bunny-2008'), false)
            ->assertSee('https://image.tmdb.org/t/p/w342/poster.jpg', false);
    });

    it('lists at most 12 titles', function () {
        foreach (range(1, 15) as $i) {
            F::publishedMovie("Film {$i}", $i, publishedAt: sprintf('2026-01-%02d 10:00:00', $i));
        }

        $html = $this->get('/')->getContent();
        preg_match('#<section aria-labelledby="derniers-ajouts".*?</section>#s', $html, $latest);

        expect(substr_count($latest[0] ?? '', 'aspect-[2/3] w-full'))->toBe(12)
            ->and($latest[0] ?? '')->toContain('Film 15')
            ->and($latest[0] ?? '')->not->toContain('Film 3<');
    });
});

describe('title page', function () {
    it('shows a movie', function () {
        $movie = F::publishedMovie('Big Buck Bunny', 10378);

        $this->get(route('titles.show', $movie->slug()->value))
            ->assertOk()
            ->assertSee('Big Buck Bunny')
            ->assertSee('(2008)')
            ->assertSee('10 min')
            ->assertSee('Synopsis de Big Buck Bunny.')
            ->assertSee('Animation')
            ->assertSee('Comédie')
            ->assertSee('https://image.tmdb.org/t/p/w500/poster.jpg', false)
            ->assertDontSee('Saisons et épisodes');
    });

    it('shows a series with its seasons and episodes', function () {
        $series = F::publishedSeries('Sintel Chronicles', 1399, seasons: 2, episodes: 3);

        $response = $this->get(route('titles.show', $series->slug()->value))
            ->assertOk()
            ->assertSee('Saisons et épisodes')
            ->assertSee('Saison 1')
            ->assertSee('Saison 2')
            ->assertSee('Épisode 1x1')
            ->assertSee('Épisode 2x3')
            ->assertSee('3 épisodes');

        expect(substr_count($response->getContent(), '<details class="rounded-lg'))->toBe(2);
    });

    it('is not found for a draft, an unpublished title, an unknown slug or an invalid slug', function () {
        $draft = F::draftMovie('Brouillon', 1);
        $unpublished = F::publishedMovie('Retiré', 2);
        $unpublished->unpublish(F::now());
        app(TitleRepository::class)->save($unpublished);

        $this->get(route('titles.show', $draft->slug()->value))->assertNotFound();
        $this->get(route('titles.show', $unpublished->slug()->value))->assertNotFound();
        $this->get('/titres/inconnu-2020')->assertNotFound();
        $this->get('/titres/Big_Buck')->assertNotFound();
        $this->get('/titres/'.str_repeat('a', 121))->assertNotFound();
    });

    it('does not use the title as a path or as markup', function (string $slug) {
        $this->get('/titres/'.$slug)->assertNotFound();
    })->with([['..%2F..%2Fetc%2Fpasswd'], ['%3Cscript%3E'], ["a'b"], ['a%20b']]);
});

describe('genre sections on the home page', function () {
    it('gives each genre its own section, with its titles only', function () {
        F::publishedMovie('Dessin animé', 1, [new Genre(16, 'Animation')]);
        F::publishedMovie('Comédie pure', 2, [new Genre(35, 'Comédie')]);

        $html = $this->get('/')->assertOk()->getContent();

        preg_match('#<section aria-labelledby="genre-16".*?</section>#s', $html, $animation);
        expect($animation[0] ?? '')->toContain('Animation')
            ->and($animation[0] ?? '')->toContain('Dessin animé')
            ->and($animation[0] ?? '')->not->toContain('Comédie pure');
    });

    it('shows a title with several genres only in its main genre', function () {
        F::publishedMovie('Big Buck Bunny', 1); // Animation puis Comédie : Animation est le genre principal

        $html = $this->get('/')->getContent();
        preg_match('#<section aria-labelledby="genre-16".*?</section>#s', $html, $animation);

        expect($animation[0] ?? '')->toContain('Big Buck Bunny')
            ->and($html)->not->toContain('id="genre-35"');
    });

    it('shows at most 12 titles in a genre section', function () {
        foreach (range(1, 30) as $i) {
            F::publishedMovie("Film {$i}", $i, [new Genre(16, 'Animation')], sprintf('2026-01-%02d 10:00:00', $i));
        }

        $html = $this->get('/')->getContent();
        preg_match('#<section aria-labelledby="genre-16".*?</section>#s', $html, $section);

        expect(substr_count($section[0] ?? '', 'aspect-[2/3] w-full'))->toBe(12);
    });

    it('opens empty without a term', function () {
        $this->get('/recherche')->assertOk()->assertSee('Saisissez un titre');
    });

    it('goes back to a clean page when the term is not text', function (string $query) {
        $this->get('/recherche?'.$query)->assertRedirect(route('search'));
    })->with([['q[]=a'], ['q[a]=b'], ['q[][]=x']]);

    it('does not fail on odd terms', function (string $term) {
        $this->get('/recherche?q='.urlencode($term))->assertOk();
    })->with([[str_repeat('é', 5000)], ["\x00\x01"], ['<script>alert(1)</script>'], ["' OR 1=1 --"], ['%'], ['\\']]);
});

describe('escaping', function () {
    beforeEach(function () {
        $this->xss = '<script>alert(1)</script>';
        $title = Title::importSeries(
            F::titleId(),
            new TmdbId(7),
            Slug::fromString('xss-2020'),
            new TitleDetails(
                name: $this->xss,
                originalName: '<b>original</b>',
                overview: '<img src=x onerror=alert(1)>',
                releaseYear: 2020,
                genres: [new Genre(16, '<i>Animation</i>')],
            ),
        );
        $title->addSeason(F::seasonId(), 1, '<u>Saison</u>', '<s>résumé</s>');
        $title->addEpisode(1, F::episodeId(), 1, '<marquee>épisode</marquee>', '<a href="javascript:alert(1)">x</a>', 24);
        $title->markEpisodePlayable(1, 1);
        $title->publish(F::now());
        app(TitleRepository::class)->save($title);
    });

    it('never outputs what TMDB sent as markup', function (string $uri) {
        $html = $this->get($uri)->assertOk()->getContent();

        foreach (['<script>alert(1)</script>', '<b>original</b>', '<img src=x', '<i>Animation</i>', '<u>Saison</u>', '<s>résumé</s>', '<marquee>', 'href="javascript:'] as $dangerous) {
            expect($html)->not->toContain($dangerous);
        }
    })->with(['/', '/titres/xss-2020', '/recherche?q=alert']);

    it('escapes it instead of dropping it', function () {
        $this->get('/titres/xss-2020')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    });

    it('does not turn a poster path from the database into an attribute injection', function () {
        DB::table('titles')->update(['poster_path' => '/x.jpg" onerror="alert(1)']);

        $html = $this->get('/titres/xss-2020')->assertOk()->getContent();

        expect($html)->not->toContain('onerror="alert(1)"')->and($html)->not->toContain('x.jpg"');
    });

    it('refuses a poster path that is a URL', function (string $path) {
        DB::table('titles')->update(['poster_path' => $path]);

        expect($this->get('/titres/xss-2020')->getContent())->not->toContain('evil.example');
    })->with([['//evil.example/x.jpg'], ['https://evil.example/x.jpg'], ['/../evil.example/x.jpg']]);
});

describe('semantic structure, on every public page', function () {
    beforeEach(function () {
        F::publishedMovie('Big Buck Bunny', 1);
        F::publishedSeries('Sintel Chronicles', 2);
    });

    it('has the landmarks, one h1 and a language', function (string $name) {
        $dom = dom($this->get(publicPages()[$name])->assertOk()->getContent());

        expect(count_of($dom, 'h1'))->toBe(1)
            ->and(count_of($dom, 'header'))->toBeGreaterThanOrEqual(1)
            ->and(count_of($dom, 'main'))->toBe(1)
            ->and(count_of($dom, 'footer'))->toBe(1)
            ->and(count_of($dom, 'nav'))->toBeGreaterThanOrEqual(1)
            ->and($dom->documentElement?->getAttribute('lang'))->not->toBe('')
            ->and($dom->getElementsByTagName('main')->item(0)?->getAttribute('id'))->toBe('contenu');
    })->with(['home', 'search', 'movie', 'series']);

    it('is responsive: viewport, skip link and a mobile menu', function (string $name) {
        $html = $this->get(publicPages()[$name])->getContent();

        expect($html)->toContain('name="viewport" content="width=device-width, initial-scale=1"')
            ->and($html)->toContain('href="#contenu"')
            ->and($html)->toContain('id="menu-mobile"');
    })->with(['home', 'movie']);

    it('declares the language of its content, French', function (string $name) {
        $dom = dom($this->get(publicPages()[$name])->getContent());

        expect($dom->documentElement?->getAttribute('lang'))->toBe('fr');
    })->with(['home', 'search', 'movie', 'series']);

    it('has a distinct title and a description', function () {
        $titles = [];

        foreach (publicPages() as $name => $uri) {
            $dom = dom($this->get($uri)->getContent());
            $titles[$name] = trim($dom->getElementsByTagName('title')->item(0)->textContent ?? '');
        }

        expect($titles['home'])->toBe(config('app.name'))
            ->and($titles['movie'])->toContain('Big Buck Bunny')
            ->and($titles['series'])->toContain('Sintel Chronicles')
            ->and(count(array_unique($titles)))->toBe(4);
    });

    it('describes the page for search engines', function () {
        $html = $this->get('/titres/big-buck-bunny-2008')->getContent();

        expect($html)->toContain('<meta name="description" content="Synopsis de Big Buck Bunny.">');
    });

    it('gives every image a text alternative', function (string $name) {
        $dom = dom($this->get(publicPages()[$name])->getContent());

        foreach ($dom->getElementsByTagName('img') as $image) {
            expect($image->hasAttribute('alt'))->toBeTrue();
        }
    })->with(['home', 'movie']);
});

describe('TMDB attribution', function () {
    it('is shown on every public page', function (string $uri) {
        F::publishedMovie('Big Buck Bunny', 1);

        $this->get($uri)->assertOk()
            ->assertSee('n\'est ni approuvé ni certifié par TMDB')
            ->assertSee('This product uses the TMDB API but is not endorsed or certified by TMDB.')
            ->assertSee('https://www.themoviedb.org/', false);
    })->with(['/', '/recherche', '/titres/big-buck-bunny-2008']);

    it('opens the TMDB link safely', function () {
        $html = $this->get('/')->getContent();

        expect($html)->toMatch('#href="https://www\.themoviedb\.org/"[^>]*rel="noopener noreferrer"#');
    });
});

describe('navigation', function () {
    it('offers login and registration to a visitor', function () {
        $this->get('/')->assertSee(route('login'), false)->assertSee(route('register'), false)->assertDontSee(route('profile.edit'), false);
    });

    it('offers the account to a logged-in user and the administration to an administrator only', function () {
        $this->actingAs(User::factory()->create())->get('/')
            ->assertSee(route('profile.edit'), false)->assertDontSee(route('admin.titles.index'), false)->assertDontSee(route('login'), false);

        $this->actingAs(User::factory()->admin()->create())->get('/')
            ->assertSee(route('admin.titles.index'), false);
    });

    it('gives a section to each genre that has a published title, and only to those', function () {
        F::publishedMovie('Visible', 1, [new Genre(16, 'Animation')]);
        F::draftMovie('Caché', 2); // Comédie, brouillon

        $html = $this->get('/')->getContent();

        expect($html)->toContain('id="genre-16"')->and($html)->not->toContain('id="genre-35"');
    });
});

describe('queries', function () {
    it('uses the same number of queries on every page whatever the size of the catalog', function (string $uri) {
        F::publishedSeries('Petite', 1, seasons: 1, episodes: 1);
        F::publishedMovie('Première', 998);

        $measure = function () use ($uri): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($uri)->assertOk();

            return count(DB::getQueryLog());
        };

        $few = $measure();

        foreach (range(10, 40) as $i) {
            F::publishedMovie("Film {$i}", $i);
        }
        F::publishedSeries('Grande', 999, seasons: 8, episodes: 10);

        expect($measure())->toBe($few);
    })->with(['/', '/recherche?q=Film', '/titres/petite-2010', '/sitemap.xml']);
});

describe('public access', function () {
    it('needs no account', function (string $uri) {
        F::publishedMovie('Big Buck Bunny', 1);

        $this->get($uri)->assertOk();
    })->with(['/', '/recherche', '/titres/big-buck-bunny-2008']);

    it('does not open the administration', function () {
        $this->get('/admin/titles')->assertRedirect('/login');
    });
});
