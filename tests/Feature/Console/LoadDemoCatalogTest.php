<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Media\Infrastructure\Queue\TranscodeMediaJob;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Symfony\Component\Process\Process;
use Tests\Support\Media\SampleVideos;
use Tests\Support\Tmdb\TmdbFixtures as Tmdb;

beforeEach(function () {
    Sleep::fake();
    Queue::fake();
    Http::preventStrayRequests();
    config(['services.tmdb.key' => '0123456789abcdef0123456789abcdef']);

    $this->media = sys_get_temp_dir().'/homeflix-demo-media-'.bin2hex(random_bytes(4));
    $this->videos = sys_get_temp_dir().'/homeflix-demo-videos-'.bin2hex(random_bytes(4));
    mkdir($this->media, 0755, true);
    mkdir($this->videos, 0755, true);
    config([
        'media.root' => $this->media,
        'demo.films' => [[
            'slug' => 'big-buck-bunny', 'search' => 'Big Buck Bunny', 'year' => 2008,
            'license' => 'CC BY 3.0', 'source' => 'https://peach.blender.org/',
            'file' => 'bbb.mp4', 'download' => 'https://download.blender.org/peach/trailer/bbb.mp4',
        ]],
    ]);

    Http::fake([
        'api.themoviedb.org/3/search/movie*' => Http::response(Tmdb::movieSearch()),
        'api.themoviedb.org/3/movie/10378*' => Http::response(Tmdb::movie()),
    ]);
});

afterEach(function () {
    (new Process(['rm', '-rf', $this->media, $this->videos]))->run();
});

it('creates the demo accounts with random passwords that are not in the repository', function () {
    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->assertSuccessful();

    $admin = User::query()->where('email', 'demo-admin@homeflix.test')->firstOrFail();
    $member = User::query()->where('email', 'demo-membre@homeflix.test')->firstOrFail();

    expect($admin->role->value)->toBe('admin')->and($member->role->value)->toBe('user')
        ->and(Hash::check('password', $admin->password))->toBeFalse()
        ->and($admin->password)->not->toBe($member->password);
});

it('does not touch accounts that already exist', function () {
    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->assertSuccessful();
    $hash = User::query()->where('email', 'demo-admin@homeflix.test')->value('password');

    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->assertSuccessful();

    expect(User::query()->where('email', 'like', 'demo-%')->count())->toBe(2)
        ->and(User::query()->where('email', 'demo-admin@homeflix.test')->value('password'))->toBe($hash);
});

it('imports the film from TMDB without duplicating it when run twice', function () {
    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->assertSuccessful();
    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->assertSuccessful();

    expect(DB::table('titles')->where('tmdb_id', 10378)->count())->toBe(1);
});

it('keeps the film as a draft and explains when there is no video', function () {
    $this->artisan('homeflix:demo', ['--videos' => $this->videos])
        ->expectsOutputToContain('Pas de vidéo')
        ->assertSuccessful();

    expect(DB::table('titles')->value('status'))->toBe('draft')->and(DB::table('media_assets')->count())->toBe(0);
});

it('sends the video through the same pipeline as the admin and queues the conversion', function () {
    if (! SampleVideos::ffmpegAvailable()) {
        $this->markTestSkipped('ffmpeg manquant');
    }

    copy(SampleVideos::video('320x180'), $this->videos.'/bbb.mp4');

    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->assertSuccessful();

    $asset = DB::table('media_assets')->first();
    expect($asset->status)->toBe('queued')->and($asset->title_id)->toBe(DB::table('titles')->value('id'));
    Queue::assertPushed(TranscodeMediaJob::class);
});

it('can be run again after the upload without sending the video twice', function () {
    if (! SampleVideos::ffmpegAvailable()) {
        $this->markTestSkipped('ffmpeg manquant');
    }

    copy(SampleVideos::video('320x180'), $this->videos.'/bbb.mp4');

    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->assertSuccessful();
    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->assertSuccessful();

    expect(DB::table('media_assets')->count())->toBe(1);
    Queue::assertPushed(TranscodeMediaJob::class, 1);
});

it('publishes the film once its video is ready', function () {
    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->assertSuccessful();
    $titleId = DB::table('titles')->value('id');
    DB::table('titles')->where('id', $titleId)->update(['is_playable' => true]);

    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->expectsOutputToContain('Publié')->assertSuccessful();

    expect(DB::table('titles')->where('id', $titleId)->value('status'))->toBe('published');
});

it('fails clearly when TMDB is not configured', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.themoviedb.org/*' => Http::response([], 401)]);

    $this->artisan('homeflix:demo', ['--videos' => $this->videos])
        ->expectsOutputToContain('TMDB')
        ->assertFailed();
});

it('reports a film TMDB does not know and exits with a failure', function () {
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.themoviedb.org/3/search/movie*' => Http::response(['results' => []])]);

    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->expectsOutputToContain('Introuvable')->assertFailed();
});

it('refuses to run in production without --force', function () {
    $this->app['env'] = 'production';

    $this->artisan('homeflix:demo', ['--videos' => $this->videos])->expectsOutputToContain('production')->assertFailed();

    expect(User::query()->where('email', 'like', 'demo-%')->count())->toBe(0);
});

describe('download', function () {
    it('downloads a missing video from the free video host only', function () {
        Http::fake(['download.blender.org/*' => Http::response('pas une vidéo', 200)]);

        $this->artisan('homeflix:demo', ['--videos' => $this->videos, '--download' => true]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://download.blender.org/peach/trailer/bbb.mp4');
        expect(is_file($this->videos.'/bbb.mp4'))->toBeTrue();
    });

    it('refuses an address on another host', function () {
        config(['demo.films.0.download' => 'https://example.org/video.mp4']);

        $this->artisan('homeflix:demo', ['--videos' => $this->videos, '--download' => true])
            ->expectsOutputToContain('refusée')->assertFailed();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'example.org'));
    });

    it('refuses a plain http address', function () {
        config(['demo.films.0.download' => 'http://download.blender.org/video.mp4']);

        $this->artisan('homeflix:demo', ['--videos' => $this->videos, '--download' => true])->assertFailed();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'blender.org'));
    });

    it('cleans up and fails when the server refuses', function () {
        Http::fake(['download.blender.org/*' => Http::response('', 404)]);

        $this->artisan('homeflix:demo', ['--videos' => $this->videos, '--download' => true])->assertFailed();

        expect(is_file($this->videos.'/bbb.mp4'))->toBeFalse();
    });

    it('does not download what is already there', function () {
        file_put_contents($this->videos.'/bbb.mp4', 'déjà là');

        $this->artisan('homeflix:demo', ['--videos' => $this->videos, '--download' => true]);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'blender.org'));
    });
});

it('only lists free films from the Blender Foundation, with a license and a source', function () {
    // La configuration livrée, pas celle des tests.
    $films = require base_path('config/demo.php');

    foreach ($films['films'] as $film) {
        expect($film['license'])->toContain('CC BY')
            ->and($film['source'])->toStartWith('https://')
            ->and(parse_url($film['download'], PHP_URL_HOST))->toBe($films['download_host']);
    }

    expect($films['films'])->not->toBeEmpty();
});
