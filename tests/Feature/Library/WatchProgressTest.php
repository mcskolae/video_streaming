<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Catalog\Domain\Model\Title;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\Support\CatalogFixtures as Catalog;

/** Un média prêt, relié à un film (`title_id`) ou à un épisode (`episode_id`). */
function libraryReadyMedia(string $kind, string $targetId, int $duration = 1000, string $status = 'ready'): string
{
    $id = Uuid::uuid7()->toString();

    DB::table('media_assets')->insert([
        'id' => $id,
        $kind === 'movie' ? 'title_id' : 'episode_id' => $targetId,
        'source_path' => "incoming/{$id}.mp4",
        'status' => $status,
        'duration_seconds' => $status === 'ready' ? $duration : null,
        'hls_master_path' => $status === 'ready' ? "hls/{$id}/master.m3u8" : null,
    ]);

    return $id;
}

function libraryProgress(string $userId, string $mediaId, int $position, int $duration = 1000, bool $completed = false, DateTimeInterface|string|null $at = null): void
{
    DB::table('watch_progress')->insert([
        'user_id' => $userId,
        'media_asset_id' => $mediaId,
        'position_seconds' => $position,
        'duration_seconds' => $duration,
        'completed' => $completed,
        'updated_at' => $at ?? now(),
    ]);
}

/**
 * Une série de deux saisons de trois épisodes, dans l'ordre 1x1 … 2x3.
 *
 * @return array{0: Title, 1: list<string>}
 */
function librarySeries(string $name = 'Sintel Chronicles', int $tmdbId = 9): array
{
    $series = Catalog::publishedSeries($name, $tmdbId, seasons: 2, episodes: 3);
    app(TitleRepository::class)->save($series);

    $episodes = DB::table('episodes')
        ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
        ->where('seasons.title_id', $series->id()->value)
        ->orderBy('seasons.number')->orderBy('episodes.number')
        ->pluck('episodes.id')->map(fn ($id) => (string) $id)->all();

    return [$series, $episodes];
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->movie = Catalog::publishedMovie('Big Buck Bunny', 1);
    $this->movieMedia = libraryReadyMedia('movie', $this->movie->id()->value);
});

describe('saving the position', function () {
    it('keeps the position of the member', function () {
        $this->actingAs($this->user)->putJson(route('progress.save', $this->movieMedia), ['position' => 123.7])->assertNoContent();

        $row = DB::table('watch_progress')->where('user_id', $this->user->id)->first();
        expect($row->position_seconds)->toBe(123)->and($row->duration_seconds)->toBe(1000)->and($row->completed)->toBeFalse();
    });

    it('replaces the previous position instead of adding a row', function () {
        $this->actingAs($this->user)->putJson(route('progress.save', $this->movieMedia), ['position' => 10])->assertNoContent();
        $this->actingAs($this->user)->putJson(route('progress.save', $this->movieMedia), ['position' => 20])->assertNoContent();

        expect(DB::table('watch_progress')->count())->toBe(1)
            ->and(DB::table('watch_progress')->value('position_seconds'))->toBe(20);
    });

    it('marks the video as completed past 90 %', function () {
        $this->actingAs($this->user)->putJson(route('progress.save', $this->movieMedia), ['position' => 901])->assertNoContent();

        expect((bool) DB::table('watch_progress')->value('completed'))->toBeTrue();
    });

    it('never goes beyond the duration the server knows', function () {
        $this->actingAs($this->user)->putJson(route('progress.save', $this->movieMedia), ['position' => 5000])->assertNoContent();

        expect(DB::table('watch_progress')->value('position_seconds'))->toBe(1000);
    });

    it('refuses invalid positions', function (mixed $position) {
        $this->actingAs($this->user)->putJson(route('progress.save', $this->movieMedia), ['position' => $position])->assertUnprocessable();

        expect(DB::table('watch_progress')->count())->toBe(0);
    })->with([-1, 'abc', null, 86401, [[1]]]);

    it('is not found for a video that is not ready, not published or unknown', function () {
        $processing = libraryReadyMedia('movie', Catalog::publishedMovie('Autre', 2)->id()->value, status: 'processing');
        $draft = Catalog::movie(tmdbId: 3, name: 'Brouillon');
        app(TitleRepository::class)->save($draft);
        $draftMedia = libraryReadyMedia('movie', $draft->id()->value);

        foreach ([$processing, $draftMedia, Uuid::uuid7()->toString()] as $media) {
            $this->actingAs($this->user)->putJson(route('progress.save', $media), ['position' => 5])->assertNotFound();
        }

        expect(DB::table('watch_progress')->count())->toBe(0);
    });

    it('is not found for an identifier that is not a UUID', function () {
        $this->actingAs($this->user)->putJson('/progression/pas-un-uuid', ['position' => 5])->assertNotFound();
    });

    it('always uses the member of the session', function () {
        $other = User::factory()->create();

        $this->actingAs($this->user)->putJson(route('progress.save', $this->movieMedia), ['position' => 5, 'user_id' => $other->id])->assertNoContent();

        expect(DB::table('watch_progress')->where('user_id', $other->id)->count())->toBe(0)
            ->and(DB::table('watch_progress')->where('user_id', $this->user->id)->count())->toBe(1);
    });

    it('sends a visitor to the login page', function () {
        $this->putJson(route('progress.save', $this->movieMedia), ['position' => 5])->assertUnauthorized();
        $this->getJson(route('progress.show', $this->movieMedia))->assertUnauthorized();
    });

    it('is limited in rate', function () {
        $url = route('progress.save', $this->movieMedia);

        foreach (range(1, 60) as $i) {
            $this->actingAs($this->user)->putJson($url, ['position' => $i])->assertNoContent();
        }

        $this->actingAs($this->user)->putJson($url, ['position' => 61])->assertStatus(429);
    });
});

describe('reading the position', function () {
    it('gives the saved position', function () {
        libraryProgress($this->user->id, $this->movieMedia, 321);

        $this->actingAs($this->user)->getJson(route('progress.show', $this->movieMedia))->assertOk()->assertExactJson(['position' => 321]);
    });

    it('gives 0 when nothing is saved', function () {
        $this->actingAs($this->user)->getJson(route('progress.show', $this->movieMedia))->assertOk()->assertExactJson(['position' => 0]);
    });

    it('gives 0 for a video that was watched to the end', function () {
        libraryProgress($this->user->id, $this->movieMedia, 950, completed: true);

        $this->actingAs($this->user)->getJson(route('progress.show', $this->movieMedia))->assertExactJson(['position' => 0]);
    });

    it('never gives the position of another member', function () {
        libraryProgress(User::factory()->create()->id, $this->movieMedia, 500);

        $this->actingAs($this->user)->getJson(route('progress.show', $this->movieMedia))->assertExactJson(['position' => 0]);
    });
});

describe('the database', function () {
    it('refuses a position beyond the duration', function () {
        libraryProgress($this->user->id, $this->movieMedia, 1001);
    })->throws(QueryException::class, 'watch_progress_position_within_duration');

    it('refuses two rows for the same member and video', function () {
        libraryProgress($this->user->id, $this->movieMedia, 1);
        libraryProgress($this->user->id, $this->movieMedia, 2);
    })->throws(QueryException::class);

    it('deletes the progress with the member', function () {
        libraryProgress($this->user->id, $this->movieMedia, 1);

        $this->user->delete();

        expect(DB::table('watch_progress')->count())->toBe(0);
    });
});

describe('continue watching', function () {
    it('lists a started movie with its progress on the home page, for a member only', function () {
        libraryProgress($this->user->id, $this->movieMedia, 250);

        $this->actingAs($this->user)->get('/')->assertOk()
            ->assertSee('Reprendre la lecture')->assertSee('Big Buck Bunny')
            ->assertSee('value="25"', false)
            ->assertSee('?lire='.$this->movieMedia, false);

        auth()->logout();
        $this->get('/')->assertOk()->assertDontSee('Reprendre la lecture');
    });

    it('drops a video that is completed', function () {
        libraryProgress($this->user->id, $this->movieMedia, 950, completed: true);

        $this->actingAs($this->user)->get('/')->assertDontSee('Reprendre la lecture');
    });

    it('drops a title that is no longer published, and a position at 0', function () {
        libraryProgress($this->user->id, $this->movieMedia, 300);
        $zero = Catalog::publishedMovie('Zéro', 5);
        libraryProgress($this->user->id, libraryReadyMedia('movie', $zero->id()->value), 0);

        DB::table('titles')->where('id', $this->movie->id()->value)->update(['status' => 'draft', 'published_at' => null]);

        $this->actingAs($this->user)->get('/')->assertDontSee('Reprendre la lecture');
    });

    it('does not show the progress of another member', function () {
        libraryProgress(User::factory()->create()->id, $this->movieMedia, 300);

        $this->actingAs($this->user)->get('/')->assertDontSee('Reprendre la lecture');
    });

    it('offers, for a series, the last episode that is not completed, once', function () {
        $series = Catalog::publishedSeries('Sintel Chronicles', 9, seasons: 2, episodes: 3);
        app(TitleRepository::class)->save($series);
        $episodes = DB::table('episodes')
            ->join('seasons', 'seasons.id', '=', 'episodes.season_id')
            ->orderBy('seasons.number')->orderBy('episodes.number')
            ->get(['episodes.id', 'seasons.number as season', 'episodes.number as episode']);

        $first = libraryReadyMedia('episode', $episodes[0]->id);   // 1x1 terminé
        $second = libraryReadyMedia('episode', $episodes[1]->id);  // 1x2 en cours, ancien
        $third = libraryReadyMedia('episode', $episodes[2]->id);   // 1x3 en cours, récent
        libraryProgress($this->user->id, $first, 950, completed: true, at: now()->subMinutes(30));
        libraryProgress($this->user->id, $second, 100, at: now()->subMinutes(20));
        libraryProgress($this->user->id, $third, 400, at: now()->subMinutes(5));

        $html = $this->actingAs($this->user)->get('/')->assertOk()->getContent();

        expect(substr_count($html, 'Sintel Chronicles'))->toBeGreaterThan(0)
            ->and($html)->toContain('?lire='.$third)
            ->and($html)->not->toContain('?lire='.$second)
            ->and($html)->not->toContain('?lire='.$first)
            ->and($html)->toContain('Épisode 1x3');
    });

    it('offers the next episode when the last one is completed', function () {
        [, $episodes] = librarySeries();
        $first = libraryReadyMedia('episode', $episodes[0]);
        $second = libraryReadyMedia('episode', $episodes[1]);
        libraryProgress($this->user->id, $first, 950, completed: true);

        $html = $this->actingAs($this->user)->get('/')->assertOk()->getContent();

        expect($html)->toContain('?lire='.$second)->and($html)->toContain('Épisode suivant : 1x2')
            ->and($html)->not->toContain('?lire='.$first);
    });

    it('goes on to the next season', function () {
        [, $episodes] = librarySeries();
        $lastOfSeasonOne = libraryReadyMedia('episode', $episodes[2]);
        $firstOfSeasonTwo = libraryReadyMedia('episode', $episodes[3]);
        libraryProgress($this->user->id, $lastOfSeasonOne, 950, completed: true);

        $html = $this->actingAs($this->user)->get('/')->getContent();

        expect($html)->toContain('?lire='.$firstOfSeasonTwo)->and($html)->toContain('Épisode suivant : 2x1');
    });

    it('skips an episode that is not ready', function () {
        [, $episodes] = librarySeries();
        $first = libraryReadyMedia('episode', $episodes[0]);
        libraryReadyMedia('episode', $episodes[1], status: 'processing');
        $third = libraryReadyMedia('episode', $episodes[2]);
        libraryProgress($this->user->id, $first, 950, completed: true);

        $this->actingAs($this->user)->get('/')->assertSee('?lire='.$third, false)->assertSee('Épisode suivant : 1x3');
    });

    it('offers nothing after the last episode', function () {
        [, $episodes] = librarySeries();
        $last = libraryReadyMedia('episode', $episodes[5]);
        libraryProgress($this->user->id, $last, 950, completed: true);

        $this->actingAs($this->user)->get('/')->assertDontSee('Reprendre la lecture');
    });

    it('offers nothing when no later episode is ready', function () {
        [, $episodes] = librarySeries();
        $first = libraryReadyMedia('episode', $episodes[0]);
        libraryReadyMedia('episode', $episodes[1], status: 'processing');
        libraryProgress($this->user->id, $first, 950, completed: true);

        $this->actingAs($this->user)->get('/')->assertDontSee('Reprendre la lecture');
    });

    it('does not offer the next episode from the progress of another member', function () {
        [, $episodes] = librarySeries();
        $first = libraryReadyMedia('episode', $episodes[0]);
        libraryReadyMedia('episode', $episodes[1]);
        libraryProgress(User::factory()->create()->id, $first, 950, completed: true);

        $this->actingAs($this->user)->get('/')->assertDontSee('Reprendre la lecture');
    });

    it('puts the most recent title first', function () {
        $other = Catalog::publishedMovie('Autre film', 7);
        $otherMedia = libraryReadyMedia('movie', $other->id()->value);
        libraryProgress($this->user->id, $this->movieMedia, 100, at: now()->subHour());
        libraryProgress($this->user->id, $otherMedia, 100, at: now()->subMinute());

        $html = $this->actingAs($this->user)->get('/')->getContent();

        expect(strpos($html, 'Autre film'))->toBeLessThan(strpos($html, 'Big Buck Bunny'));
    });
});

describe('the player page', function () {
    it('gives the progress URL template to a member only', function () {
        $url = route('titles.show', $this->movie->slug()->value);

        $this->actingAs($this->user)->get($url)->assertOk()->assertSee('data-progress-url="'.url('/progression/__ID__').'"', false);

        auth()->logout();
        $this->get($url)->assertOk()->assertDontSee('data-progress-url', false);
    });
});

describe('progress on the title page', function () {
    it('shows where the member is under each episode, with a resume link', function () {
        [$series, $episodes] = librarySeries();
        $first = libraryReadyMedia('episode', $episodes[0], 2700);
        $second = libraryReadyMedia('episode', $episodes[1], 2700);
        libraryReadyMedia('episode', $episodes[2], 2700);
        libraryProgress($this->user->id, $first, 2600, 2700, completed: true, at: now()->subHour());
        libraryProgress($this->user->id, $second, 750, 2700);

        $html = $this->actingAs($this->user)->get(route('titles.show', $series->slug()->value))->assertOk()->getContent();

        expect($html)->toContain('Reprendre où vous en êtes')->and($html)->toContain('Épisode 1x2')
            ->and($html)->toContain('?lire='.$second.'#lecture')
            ->and($html)->toContain('12 min 30 s sur 45 min')
            ->and($html)->toContain('Vu')
            ->and(substr_count($html, 'data-episode-progress'))->toBe(2);
    });

    it('suggests the next episode when the last one is completed', function () {
        [$series, $episodes] = librarySeries();
        $first = libraryReadyMedia('episode', $episodes[0]);
        $second = libraryReadyMedia('episode', $episodes[1]);
        libraryProgress($this->user->id, $first, 950, completed: true);

        $this->actingAs($this->user)->get(route('titles.show', $series->slug()->value))
            ->assertSee('Reprendre où vous en êtes')->assertSee('Épisode suivant : 1x2')->assertSee('Lire la suite')
            ->assertSee('?lire='.$second, false);
    });

    it('resumes a movie at its position', function () {
        libraryProgress($this->user->id, $this->movieMedia, 250);

        $this->actingAs($this->user)->get(route('titles.show', $this->movie->slug()->value))
            ->assertSee('Reprendre où vous en êtes')->assertSee('À 4 min 10 s');
    });

    it('shows nothing to a visitor, to a member who has not started, or about another member', function () {
        [$series, $episodes] = librarySeries();
        $first = libraryReadyMedia('episode', $episodes[0]);
        libraryProgress(User::factory()->create()->id, $first, 500);
        $url = route('titles.show', $series->slug()->value);

        $this->get($url)->assertOk()->assertDontSee('Reprendre où vous en êtes')->assertDontSee('data-episode-progress', false);
        $this->actingAs($this->user)->get($url)->assertOk()->assertDontSee('Reprendre où vous en êtes')->assertDontSee('data-episode-progress', false);
    });
});
