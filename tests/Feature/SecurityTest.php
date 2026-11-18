<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Livewire\Livewire;
use Tests\Support\CatalogFixtures as F;

/** Active le contrôle CSRF, que Laravel désactive pendant les tests. */
function enforceCsrf(): void
{
    app()->bind(PreventRequestForgery::class, fn (Application $app) => new class($app, $app->make(Encrypter::class)) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });
}

/** @return array{string, string|null, string} en-tête, nonce, page */
function csp(string $uri, ?User $user = null): array
{
    $response = ($user ? test()->actingAs($user) : test())->get($uri)->assertOk();
    $header = $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", (string) $header, $match);

    return [$header, $match[1] ?? null, $response->getContent()];
}

describe('content security policy', function () {
    it('is sent on public and admin pages, and on auth pages', function (string $uri) {
        $admin = $uri === '/login' ? null : User::factory()->admin()->create();

        [$header] = csp($uri, $admin);

        expect($header)->toContain("default-src 'self'")
            ->and($header)->toContain("object-src 'none'")
            ->and($header)->toContain("frame-ancestors 'none'")
            ->and($header)->toContain("base-uri 'self'")
            ->and($header)->toContain("form-action 'self'");
    })->with(['/', '/login', '/admin/titles']);

    it('allows no inline script without the nonce and no script from another site', function () {
        [$header] = csp('/');
        $scriptSrc = collect(explode(';', $header))->first(fn ($directive) => str_contains($directive, 'script-src'));

        expect($scriptSrc)->not->toContain("'unsafe-inline'")
            ->and($scriptSrc)->not->toContain('http')
            ->and($scriptSrc)->not->toContain('*');
    });

    it('gives the nonce to every script and style of the page', function () {
        app(TitleRepository::class)->save(F::publishedSeries());

        [$header, $nonce, $html] = csp('/titres/sintel-chronicles-2010');

        expect($nonce)->not->toBeNull()->and(strlen($nonce))->toBeGreaterThanOrEqual(16);

        preg_match_all('/<script\b[^>]*>/i', $html, $scripts);
        preg_match_all('/<style\b[^>]*>/i', $html, $styles);

        foreach ([...$scripts[0], ...$styles[0]] as $tag) {
            expect($tag)->toContain("nonce=\"{$nonce}\"");
        }
    });

    it('uses a new nonce for every response', function () {
        [, $first] = csp('/');
        [, $second] = csp('/');

        expect($first)->not->toBe($second);
    });

    it('allows the font files that the font stylesheet points to', function () {
        [$header] = csp('/');
        $fontSrc = collect(explode(';', $header))->first(fn ($directive) => str_contains($directive, 'font-src'));

        expect($fontSrc)->toContain('https://fonts.bunny.net')->toContain('https://cdn.jsdelivr.net');
    });

    it('allows the TMDB images and the video blobs the player needs', function () {
        [$header] = csp('/');

        expect($header)->toContain('img-src')->toContain('https://image.tmdb.org')
            ->and($header)->toContain('media-src')->toContain('blob:');
    });
});

describe('csrf', function () {
    it('refuses a form without a token', function (string $method, string $uri) {
        enforceCsrf();

        $this->actingAs(User::factory()->admin()->create())->{$method}($uri)->assertStatus(419);
    })->with([
        'login' => ['post', '/login'],
        'logout' => ['post', '/logout'],
        'import' => ['post', '/admin/titles/import'],
        'import series' => ['post', '/admin/titles/import/series'],
        'publish' => ['post', '/admin/titles/01900000-0000-7000-8000-000000000000/publish'],
        'start upload' => ['post', '/admin/media/uploads'],
    ]);

    it('refuses a Livewire update without a token', function () {
        enforceCsrf();

        $this->postJson(Livewire::getUpdateUri(), ['components' => []])->assertStatus(419);
    });
});
