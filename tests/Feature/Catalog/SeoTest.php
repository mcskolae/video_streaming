<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Catalog\Domain\Model\Genre;
use Tests\Support\CatalogFixtures as F;

/**
 * Le contenu JSON-LD d'une page, décodé.
 *
 * @return array<string, mixed>
 */
function structuredData(string $html): array
{
    preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $match);

    return json_decode($match[1] ?? 'null', true, flags: JSON_THROW_ON_ERROR) ?? [];
}

describe('sitemap.xml', function () {
    it('lists the home page, the genres and the published titles, never the drafts', function () {
        F::publishedMovie('Big Buck Bunny', 1);
        F::publishedSeries('Sintel Chronicles', 2);
        F::draftMovie('Film secret', 3);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        expect($xml)->not->toBeFalse();

        $locations = array_map('strval', $xml->xpath('//*[local-name()="loc"]'));

        expect($locations)->toContain(route('home'))
            ->toContain(route('genres.show', 16))
            ->toContain(route('titles.show', 'big-buck-bunny-2008'))
            ->toContain(route('titles.show', 'sintel-chronicles-2010'))
            ->and(implode(' ', $locations))->not->toContain('film-secret');
    });

    it('gives each title its last modification date', function () {
        F::publishedMovie('Big Buck Bunny', 1);

        $xml = simplexml_load_string($this->get('/sitemap.xml')->getContent());
        $lastmod = (string) ($xml->xpath('//*[local-name()="lastmod"]')[0] ?? '');

        expect(DateTimeImmutable::createFromFormat(DATE_ATOM, $lastmod))->not->toBeFalse();
    });

    it('is valid on an empty catalog', function () {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());

        expect($xml->xpath('//*[local-name()="loc"]'))->toHaveCount(1);
    });
});

describe('robots.txt', function () {
    it('points to the sitemap with an absolute address and keeps robots out of private pages', function () {
        $body = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();

        expect($body)->toContain('User-agent: *')
            ->toContain('Sitemap: '.route('sitemap'))
            ->toContain('Disallow: /admin')
            ->toContain('Disallow: /favoris')
            ->toContain('Disallow: /hls/')
            ->and(route('sitemap'))->toStartWith('http');
    });
});

describe('meta tags', function () {
    it('gives every public page a canonical address and an Open Graph preview', function (string $uri) {
        F::publishedMovie('Big Buck Bunny', 1);

        $html = $this->get($uri)->assertOk()->getContent();

        expect($html)->toContain('<link rel="canonical" href="')
            ->toContain('<meta property="og:title"')
            ->toContain('<meta property="og:url"')
            ->toContain('<meta property="og:site_name" content="'.config('app.name').'">');
    })->with(['/', '/genres/16', '/titres/big-buck-bunny-2008']);

    it('drops the query string from the canonical address', function () {
        $this->get('/?utm_source=x')->assertSee('<link rel="canonical" href="'.url('/').'">', false);
    });

    it('keeps the page number in the canonical address of a genre page after the first', function () {
        foreach (range(1, 30) as $i) {
            F::publishedMovie("Film {$i}", $i, [new Genre(16, 'Animation')]);
        }

        $this->get('/genres/16')->assertSee('<link rel="canonical" href="'.route('genres.show', 16).'">', false);
        $this->get('/genres/16?page=2')->assertSee('<link rel="canonical" href="'.route('genres.show', 16).'?page=2">', false);
        $this->get('/genres/16?page=abc')->assertSee('<link rel="canonical" href="'.route('genres.show', 16).'">', false);
    });

    it('describes a movie with its poster for a shared link', function () {
        F::publishedMovie('Big Buck Bunny', 1);

        $this->get('/titres/big-buck-bunny-2008')
            ->assertSee('<meta property="og:type" content="video.movie">', false)
            ->assertSee('<meta property="og:image" content="https://image.tmdb.org/t/p/w500/poster.jpg">', false)
            ->assertSee('<meta property="og:description" content="Synopsis de Big Buck Bunny.">', false);
    });

    it('marks a series as a TV show', function () {
        F::publishedSeries('Sintel Chronicles', 2);

        $this->get('/titres/sintel-chronicles-2010')->assertSee('<meta property="og:type" content="video.tv_show">', false);
    });

    it('asks search engines not to index the search results and the favorites', function () {
        $this->get('/recherche?q=x')->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->actingAs(User::factory()->create())->get('/favoris')->assertSee('<meta name="robots" content="noindex, follow">', false);
    });

    it('lets search engines index the catalog pages', function () {
        F::publishedMovie('Big Buck Bunny', 1);

        foreach (['/', '/genres/16', '/titres/big-buck-bunny-2008'] as $uri) {
            $this->get($uri)->assertDontSee('noindex', false);
        }
    });
});

describe('structured data', function () {
    it('describes a movie with schema.org', function () {
        F::publishedMovie('Big Buck Bunny', 1);

        $data = structuredData($this->get('/titres/big-buck-bunny-2008')->getContent());

        expect($data)->toMatchArray([
            '@context' => 'https://schema.org',
            '@type' => 'Movie',
            'name' => 'Big Buck Bunny',
            'url' => route('titles.show', 'big-buck-bunny-2008'),
            'image' => 'https://image.tmdb.org/t/p/w500/poster.jpg',
            'datePublished' => '2008',
            'duration' => 'PT10M',
        ])->and($data['genre'])->toContain('Animation');
    });

    it('describes a series with its number of seasons and episodes', function () {
        F::publishedSeries('Sintel Chronicles', 2, seasons: 2, episodes: 3);

        $data = structuredData($this->get('/titres/sintel-chronicles-2010')->getContent());

        expect($data)->toMatchArray(['@type' => 'TVSeries', 'numberOfSeasons' => 2, 'numberOfEpisodes' => 6])
            ->and($data)->not->toHaveKey('duration');
    });

    it('cannot be closed by a title coming from TMDB', function () {
        $movie = F::publishedMovie('</script><script>alert(1)</script>', 1);

        $html = $this->get(route('titles.show', $movie->slug()->value))->assertOk()->getContent();

        expect($html)->not->toContain('<script>alert(1)')
            ->and(structuredData($html)['name'])->toBe('</script><script>alert(1)</script>');
    });
});
