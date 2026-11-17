<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Catalog\Domain\Model\Slug;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Model\TitleDetails;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\CatalogFixtures as F;
use Tests\Support\Tmdb\TmdbFixtures as Tmdb;

beforeEach(function () {
    Sleep::fake();
    Http::preventStrayRequests();
    config(['services.tmdb.key' => '0123456789abcdef0123456789abcdef']);
    $this->admin = User::factory()->admin()->create();
});

function fakeTmdbMovie(): void
{
    Http::fake([
        'api.themoviedb.org/3/search/movie*' => Http::response(Tmdb::movieSearch()),
        'api.themoviedb.org/3/movie/10378*' => Http::response(Tmdb::movie()),
        'api.themoviedb.org/3/movie/*' => Http::response([], 404),
    ]);
}

function savedMovie(string $name = 'Big Buck Bunny', int $tmdbId = 10378, bool $playable = false): Title
{
    $title = F::movie(tmdbId: $tmdbId, name: $name);

    if ($playable) {
        $title->markPlayable();
    }

    app(TitleRepository::class)->save($title);

    return $title;
}

describe('access', function () {
    $routes = [
        'list' => ['get', '/admin/titles'],
        'search' => ['get', '/admin/titles/import'],
        'preview' => ['get', '/admin/titles/import/10378'],
        'import' => ['post', '/admin/titles/import'],
        'preview series' => ['get', '/admin/titles/import/series/1399'],
        'import series' => ['post', '/admin/titles/import/series'],
        'show series' => ['get', '/admin/titles/01900000-0000-7000-8000-000000000000'],
        'publish' => ['post', '/admin/titles/01900000-0000-7000-8000-000000000000/publish'],
        'unpublish' => ['post', '/admin/titles/01900000-0000-7000-8000-000000000000/unpublish'],
    ];

    it('sends a visitor to the login page', function (string $method, string $uri) {
        $this->{$method}($uri)->assertRedirect('/login');
        Http::assertNothingSent();
    })->with($routes);

    it('refuses a simple user with a 403', function (string $method, string $uri) {
        $this->actingAs(User::factory()->create())->{$method}($uri)->assertForbidden();
        Http::assertNothingSent();
    })->with($routes);

    it('lets an administrator reach every route', function (string $method, string $uri) {
        fakeTmdbMovie();

        expect([401, 403])->not->toContain($this->actingAs($this->admin)->{$method}($uri)->status());
    })->with($routes);

    it('shows the catalog link only to administrators', function () {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()->assertDontSee(route('admin.titles.index'), false);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()->assertSee(route('admin.titles.index'), false);
    });
});

describe('list', function () {
    it('shows an empty catalog', function () {
        $this->actingAs($this->admin)->get('/admin/titles')->assertOk()->assertSee('Le catalogue est vide');
    });

    it('shows the imported titles with their state', function () {
        savedMovie('Big Buck Bunny');

        $this->actingAs($this->admin)->get('/admin/titles')
            ->assertOk()
            ->assertSee('Big Buck Bunny')
            ->assertSee('Brouillon')
            ->assertSee('https://image.tmdb.org/t/p/w92/poster.jpg', false);
    });

    it('offers to publish only a title whose media is ready', function () {
        $ready = savedMovie('Prêt', 1, playable: true);
        $notReady = savedMovie('Pas prêt', 2);

        $response = $this->actingAs($this->admin)->get('/admin/titles')->assertOk();

        $response->assertSee(route('admin.titles.publish', $ready->id()->value), false)
            ->assertDontSee(route('admin.titles.publish', $notReady->id()->value), false)
            ->assertSee('Publication impossible')
            ->assertSee('name="_token"', false);
    });

    it('offers to unpublish a published title', function () {
        $title = savedMovie('Publié', 1, playable: true);
        $title->publish(F::now());
        app(TitleRepository::class)->save($title);

        $this->actingAs($this->admin)->get('/admin/titles')
            ->assertSee('Publié')
            ->assertSee(route('admin.titles.unpublish', $title->id()->value), false);
    });

    it('escapes what TMDB sends', function () {
        app(TitleRepository::class)->save(Title::importMovie(
            F::titleId(),
            new TmdbId(1),
            Slug::fromString('xss-2020'),
            new TitleDetails(name: '<script>alert(1)</script>', releaseYear: 2020),
        ));

        $this->actingAs($this->admin)->get('/admin/titles')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    });

    it('paginates and tolerates a wrong page number', function (string $page, int $expected) {
        foreach (range(1, 25) as $i) {
            savedMovie("Film {$i}", $i);
        }

        $response = $this->actingAs($this->admin)->get('/admin/titles?page='.$page)->assertOk();

        expect(preg_match_all('/<td class="px-4 py-3">\s*<div class="flex items-center gap-3">/', $response->getContent()))->toBe($expected);
    })->with([['1', 20], ['2', 5], ['abc', 20], ['-3', 20], ['0', 20], ['99', 0]]);
});

describe('search', function () {
    it('lists the movies found, flags the ones already imported and escapes them', function () {
        Http::fake(['api.themoviedb.org/3/search/movie*' => Http::response(['results' => [
            ['id' => 10378, 'title' => 'Big Buck Bunny', 'overview' => 'Un lapin.', 'release_date' => '2008-04-10', 'poster_path' => '/poster.jpg'],
            ['id' => 2, 'title' => '<img src=x onerror=alert(1)>', 'overview' => '<b>gras</b>', 'release_date' => '', 'poster_path' => null],
        ]])]);
        savedMovie('Big Buck Bunny', 10378);

        $this->actingAs($this->admin)->get('/admin/titles/import?query=bunny')
            ->assertOk()
            ->assertSee('Big Buck Bunny')
            ->assertSee('Déjà au catalogue')
            ->assertSee(route('admin.titles.preview', 10378), false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<b>gras</b>', false);
    });

    it('does not call TMDB for an empty search', function () {
        $this->actingAs($this->admin)->get('/admin/titles/import')->assertOk()->assertSee('Rechercher');
        $this->actingAs($this->admin)->get('/admin/titles/import?query=%20%20')->assertOk();

        Http::assertNothingSent();
    });

    it('says when nothing was found', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['results' => []])]);

        $this->actingAs($this->admin)->get('/admin/titles/import?query=zzz')->assertOk()->assertSee('Aucun film trouvé');
    });

    it('refuses a query longer than 200 characters', function () {
        $this->actingAs($this->admin)->get('/admin/titles/import?query='.str_repeat('a', 201))
            ->assertSessionHasErrors('query');

        Http::assertNothingSent();
    });

    it('shows a clear message instead of an error page when TMDB is down', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response([], 500)]);

        $this->actingAs($this->admin)->get('/admin/titles/import?query=bunny')
            ->assertOk()
            ->assertSee('TMDB est indisponible')
            ->assertDontSee('0123456789abcdef');
    });

    it('shows a clear message when the TMDB key is missing', function () {
        config(['services.tmdb.key' => null]);

        $this->actingAs($this->admin)->get('/admin/titles/import?query=bunny')
            ->assertOk()->assertSee('TMDB est indisponible ou mal configuré');
        Http::assertNothingSent();
    });

    it('limits the number of TMDB searches', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response(['results' => []])]);
        $this->actingAs($this->admin);

        foreach (range(1, 30) as $i) {
            $this->get('/admin/titles/import?query=film'.$i)->assertOk();
        }

        $this->get('/admin/titles/import?query=encore')->assertStatus(429);
    });
});

describe('preview', function () {
    it('shows the details of a movie before importing it', function () {
        fakeTmdbMovie();

        $this->actingAs($this->admin)->get('/admin/titles/import/10378')
            ->assertOk()
            ->assertSee('Big Buck Bunny')
            ->assertSee('(2008)')
            ->assertSee('10 min')
            ->assertSee('Animation, Comédie')
            ->assertSee('Importer en brouillon')
            ->assertSee('https://image.tmdb.org/t/p/w342/poster.jpg', false);
    });

    it('offers to update a movie that is already imported', function () {
        fakeTmdbMovie();
        savedMovie();

        $this->actingAs($this->admin)->get('/admin/titles/import/10378')
            ->assertOk()->assertSee('Mettre à jour la fiche')->assertDontSee('Importer en brouillon');
    });

    it('goes back to the search with a message for an unknown movie', function () {
        fakeTmdbMovie();

        $this->actingAs($this->admin)->get('/admin/titles/import/999')
            ->assertRedirect(route('admin.titles.search'))
            ->assertSessionHas('error', 'Ce film est introuvable sur TMDB.');
    });

    it('goes back to the search with a message when TMDB is down', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response([], 503)]);

        $this->actingAs($this->admin)->get('/admin/titles/import/10378')
            ->assertRedirect(route('admin.titles.search'))
            ->assertSessionHas('error');
    });

    it('is not found for an identifier that is not a positive number', function (string $id) {
        $this->actingAs($this->admin)->get('/admin/titles/import/'.$id)->assertNotFound();
        Http::assertNothingSent();
    })->with(['abc', '0', '-1', '1.5', '99999999999', '01']);
});

describe('import', function () {
    it('imports a movie as a draft and finds it in the list', function () {
        fakeTmdbMovie();

        $this->actingAs($this->admin)->post('/admin/titles/import', ['tmdb_id' => 10378])
            ->assertRedirect(route('admin.titles.index'))
            ->assertSessionHas('status', 'Film importé en brouillon.');

        $this->get('/admin/titles')->assertSee('Big Buck Bunny')->assertSee('Brouillon');
        expect(DB::table('titles')->count())->toBe(1)
            ->and(DB::table('titles')->value('status'))->toBe('draft')
            ->and(DB::table('titles')->value('is_playable'))->toBeFalse();
    });

    it('does not create a duplicate when the same movie is imported twice', function () {
        fakeTmdbMovie();
        $this->actingAs($this->admin);

        $this->post('/admin/titles/import', ['tmdb_id' => 10378]);
        $this->post('/admin/titles/import', ['tmdb_id' => 10378])
            ->assertSessionHas('status', 'Ce film était déjà au catalogue : sa fiche a été mise à jour.');

        expect(DB::table('titles')->count())->toBe(1)
            ->and(DB::table('title_genre')->count())->toBe(2);
    });

    it('reports an unknown movie without creating anything', function () {
        fakeTmdbMovie();

        $this->actingAs($this->admin)->from('/admin/titles/import')->post('/admin/titles/import', ['tmdb_id' => 999])
            ->assertRedirect('/admin/titles/import')
            ->assertSessionHas('error', 'Ce film est introuvable sur TMDB.');

        expect(DB::table('titles')->count())->toBe(0);
    });

    it('reports TMDB being down without a server error and without creating anything', function () {
        Http::fake(['api.themoviedb.org/*' => Http::response([], 500)]);

        $this->actingAs($this->admin)->post('/admin/titles/import', ['tmdb_id' => 10378])
            ->assertSessionHas('error');

        expect(DB::table('titles')->count())->toBe(0);
    });

    it('reports a missing TMDB key', function () {
        config(['services.tmdb.key' => null]);

        $this->actingAs($this->admin)->post('/admin/titles/import', ['tmdb_id' => 10378])
            ->assertSessionHas('error');
        Http::assertNothingSent();
    });

    it('refuses an invalid TMDB id without calling TMDB', function (mixed $id) {
        $this->actingAs($this->admin)->post('/admin/titles/import', ['tmdb_id' => $id])
            ->assertSessionHasErrors('tmdb_id');

        Http::assertNothingSent();
        expect(DB::table('titles')->count())->toBe(0);
    })->with([['abc'], ['0'], ['-5'], ['1.5'], ['2147483648'], ['99999999999999999999'], [''], [null], [[1, 2]]]);

    it('sends no secret to TMDB in the body and uses the configured host', function () {
        fakeTmdbMovie();

        $this->actingAs($this->admin)->post('/admin/titles/import', ['tmdb_id' => 10378, 'host' => 'evil.example']);

        Http::assertSent(fn (Request $request) => parse_url($request->url(), PHP_URL_HOST) === 'api.themoviedb.org');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'evil.example'));
    });

    it('limits the number of imports', function () {
        fakeTmdbMovie();
        $this->actingAs($this->admin);

        foreach (range(1, 30) as $i) {
            $this->post('/admin/titles/import', ['tmdb_id' => 10378])->assertRedirect();
        }

        $this->post('/admin/titles/import', ['tmdb_id' => 10378])->assertStatus(429);
    });
});

describe('publish and unpublish', function () {
    it('refuses to publish a title with no ready media', function () {
        $title = savedMovie();

        $this->actingAs($this->admin)->from('/admin/titles')->post(route('admin.titles.publish', $title->id()->value))
            ->assertRedirect('/admin/titles')
            ->assertSessionHas('error', 'Publication impossible : aucun média n\'est prêt pour ce titre.');

        expect(DB::table('titles')->value('status'))->toBe('draft');
    });

    it('publishes a title whose media is ready', function () {
        $title = savedMovie(playable: true);

        $this->actingAs($this->admin)->post(route('admin.titles.publish', $title->id()->value))
            ->assertSessionHas('status', 'Titre publié.');

        expect(DB::table('titles')->value('status'))->toBe('published')
            ->and(DB::table('titles')->value('published_at'))->not->toBeNull();
    });

    it('sends a published title back to draft', function () {
        $title = savedMovie(playable: true);
        $this->actingAs($this->admin)->post(route('admin.titles.publish', $title->id()->value));

        $this->post(route('admin.titles.unpublish', $title->id()->value))
            ->assertSessionHas('status', 'Titre repassé en brouillon.');

        expect(DB::table('titles')->value('status'))->toBe('draft')
            ->and(DB::table('titles')->value('published_at'))->toBeNull();
    });

    it('is not found for an unknown title', function (string $action) {
        $this->actingAs($this->admin)->post("/admin/titles/01900000-0000-7000-8000-000000000000/{$action}")->assertNotFound();
    })->with(['publish', 'unpublish']);

    it('is not found for an identifier that is not a UUID', function (string $action) {
        $this->actingAs($this->admin)->post("/admin/titles/not-a-uuid/{$action}")->assertNotFound();
    })->with(['publish', 'unpublish']);

    it('does not accept GET for state changes', function (string $action) {
        $title = savedMovie(playable: true);

        $this->actingAs($this->admin)->get(route("admin.titles.{$action}", $title->id()->value))->assertStatus(405);
        expect(DB::table('titles')->value('status'))->toBe('draft');
    })->with(['publish', 'unpublish']);
});

describe('protection', function () {
    it('protects every route under /admin/titles with auth, admin and the web group', function () {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'admin/titles'));

        expect($routes)->toHaveCount(9);

        foreach ($routes as $route) {
            expect($route->gatherMiddleware())->toContain('auth', 'admin', 'web');
        }
    });

    it('includes the CSRF token in every form of the catalog pages', function () {
        fakeTmdbMovie();
        savedMovie(playable: true);
        $this->actingAs($this->admin);

        foreach (['/admin/titles', '/admin/titles/import/10378'] as $uri) {
            $html = $this->get($uri)->getContent();

            expect(substr_count($html, '<form method="POST"'))->toBeGreaterThan(0)
                ->and(substr_count($html, 'name="_token"'))->toBeGreaterThanOrEqual(substr_count($html, '<form method="POST"'));
        }

    });
});

function fakeTmdbSeries(): void
{
    Http::fake([
        'api.themoviedb.org/3/search/tv*' => Http::response(['results' => [['id' => 1399, 'name' => 'Sintel Chronicles', 'original_name' => 'Sintel Chronicles', 'overview' => 'Une guerrière.', 'first_air_date' => '2010-09-27', 'poster_path' => null]]]),
        'api.themoviedb.org/3/tv/1399/season/*' => fn (Request $request) => Http::response(Tmdb::season((int) basename(parse_url($request->url(), PHP_URL_PATH)))),
        'api.themoviedb.org/3/tv/1399*' => Http::response(Tmdb::series()),
        'api.themoviedb.org/3/tv/*' => Http::response([], 404),
    ]);
}

describe('series', function () {
    it('searches series, not movies, when asked', function () {
        fakeTmdbSeries();

        $this->actingAs($this->admin)->get('/admin/titles/import?type=series&query=sintel')
            ->assertOk()
            ->assertSee('Importer une série depuis TMDB')
            ->assertSee(route('admin.titles.preview-series', 1399), false);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/search/tv'));
    });

    it('searches movies by default and refuses an unknown type', function () {
        fakeTmdbMovie();

        $this->actingAs($this->admin)->get('/admin/titles/import?query=big')->assertOk()->assertSee(route('admin.titles.preview', 10378), false);
        $this->actingAs($this->admin)->get('/admin/titles/import?type=anime&query=big')->assertSessionHasErrors('type');
    });

    it('previews a series with its season and episode counts', function () {
        fakeTmdbSeries();

        $this->actingAs($this->admin)->get('/admin/titles/import/series/1399')
            ->assertOk()
            ->assertSee('Sintel Chronicles')
            ->assertSee('Importer en brouillon');
    });

    it('imports a series with all its seasons and episodes, and lands on its page', function () {
        fakeTmdbSeries();

        $response = $this->actingAs($this->admin)->post('/admin/titles/import/series', ['tmdb_id' => 1399]);

        $titleId = DB::table('titles')->where('tmdb_id', 1399)->where('type', 'series')->value('id');
        $response->assertRedirect(route('admin.titles.show', $titleId))->assertSessionHas('status');
        expect(DB::table('seasons')->where('title_id', $titleId)->count())->toBe(3)
            ->and(DB::table('episodes')->count())->toBe(6);
    });

    it('does not create duplicates when a series is imported again', function () {
        fakeTmdbSeries();

        $this->actingAs($this->admin)->post('/admin/titles/import/series', ['tmdb_id' => 1399]);
        $this->actingAs($this->admin)->post('/admin/titles/import/series', ['tmdb_id' => 1399]);

        expect(DB::table('titles')->count())->toBe(1)
            ->and(DB::table('seasons')->count())->toBe(3)
            ->and(DB::table('episodes')->count())->toBe(6);
    });

    it('explains when TMDB does not know the series', function () {
        fakeTmdbSeries();

        $this->actingAs($this->admin)->from('/admin/titles/import/series/7')->post('/admin/titles/import/series', ['tmdb_id' => 7])
            ->assertRedirect('/admin/titles/import/series/7')
            ->assertSessionHas('error', 'Cette série est introuvable sur TMDB.');
    });

    it('lists seasons and episodes with an upload link for each episode', function () {
        fakeTmdbSeries();
        $this->actingAs($this->admin)->post('/admin/titles/import/series', ['tmdb_id' => 1399]);
        $titleId = DB::table('titles')->value('id');
        $episodeId = DB::table('episodes')->orderBy('number')->value('id');

        $this->actingAs($this->admin)->get(route('admin.titles.show', $titleId))
            ->assertOk()
            ->assertSee('Saison 2')
            ->assertSee('Pilote')
            ->assertSee(route('admin.media.show', ['kind' => 'episode', 'target' => $episodeId]), false)
            ->assertSee('Envoyez la vidéo d\'au moins un épisode');

        $this->actingAs($this->admin)->get(route('admin.media.show', ['kind' => 'episode', 'target' => $episodeId]))->assertOk();
    });

    it('offers to publish once an episode is playable', function () {
        fakeTmdbSeries();
        $this->actingAs($this->admin)->post('/admin/titles/import/series', ['tmdb_id' => 1399]);
        $titleId = DB::table('titles')->value('id');
        DB::table('episodes')->limit(1)->update(['is_playable' => true]);

        $this->actingAs($this->admin)->get(route('admin.titles.show', $titleId))
            ->assertSee(route('admin.titles.publish', $titleId), false);
    });

    it('is not found for a movie or an unknown title', function () {
        $movie = savedMovie();

        $this->actingAs($this->admin)->get(route('admin.titles.show', $movie->id()->value))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.titles.show', '01900000-0000-7000-8000-000000000000'))->assertNotFound();
    });

    it('links a series to its episodes from the catalog list', function () {
        fakeTmdbSeries();
        $this->actingAs($this->admin)->post('/admin/titles/import/series', ['tmdb_id' => 1399]);
        $titleId = DB::table('titles')->value('id');

        $this->actingAs($this->admin)->get('/admin/titles')->assertSee(route('admin.titles.show', $titleId), false);
    });
});
