<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Media\Domain\Event\MediaAssetReady;
use HomeFlix\Media\Domain\Model\Duration;
use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableId;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Domain\Model\RelativePath;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use HomeFlix\Shared\Application\EventDispatcher;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\Process\Process;
use Tests\Support\CatalogFixtures as Catalog;
use Tests\Support\Media\SampleVideos;
use Tests\Support\MediaFixtures as M;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/homeflix-stream-'.bin2hex(random_bytes(4));
    mkdir($this->root, 0755, true);
    config(['media.root' => $this->root]);
    $this->admin = User::factory()->admin()->create();
});

afterEach(function () {
    (new Process(['rm', '-rf', $this->root]))->run();
});

/** Un média prêt pour un film ou un épisode, avec ses enregistrements en base. */
function readyMedia(PlayableRef $target, ?string $masterPath = null): MediaAsset
{
    $id = M::assetId();
    $asset = MediaAsset::reconstitute(
        $id, $target, new RelativePath('incoming/x/source.mp4'), MediaStatus::Ready,
        new Duration(600), null, new RelativePath($masterPath ?? 'hls/'.$id->value.'/master.m3u8'), [M::rendition()],
    );
    app(MediaAssetRepository::class)->save($asset);

    return $asset;
}

function titleRef(string $id): PlayableRef
{
    return PlayableRef::title(PlayableId::fromString($id));
}

describe('the event bus makes titles playable', function () {
    it('makes a movie playable when its media becomes ready', function () {
        $movie = Catalog::draftMovie('Film', 1);
        $asset = readyMedia(titleRef($movie->id()->value));

        app(EventDispatcher::class)->dispatch(new MediaAssetReady($asset->id(), $asset->target(), M::now()));

        expect((bool) DB::table('titles')->where('id', $movie->id()->value)->value('is_playable'))->toBeTrue();
    });

    it('makes an episode and its series playable', function () {
        $series = Catalog::series(tmdbId: 5, name: 'Série');
        app(TitleRepository::class)->save($series);
        $episode = $series->season(1)->episode(1);
        $asset = readyMedia(PlayableRef::episode(PlayableId::fromString($episode->id->value)));

        app(EventDispatcher::class)->dispatch(new MediaAssetReady($asset->id(), $asset->target(), M::now()));

        expect((bool) DB::table('episodes')->where('id', $episode->id->value)->value('is_playable'))->toBeTrue()
            ->and(DB::table('episodes')->where('is_playable', true)->count())->toBe(1);
    });

    it('does not fail when the title has been deleted in the meantime', function () {
        $asset = M::uploaded(titleRef(M::playableId()->value));

        app(EventDispatcher::class)->dispatch(new MediaAssetReady($asset->id(), $asset->target(), M::now()));

        expect(DB::table('titles')->count())->toBe(0);
    });
});

describe('from the upload to the public player', function () {
    it('takes a real video all the way to a published movie that visitors can play', function () {
        if (! SampleVideos::ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg et ffprobe ne sont pas installés.');
        }

        config(['queue.default' => 'sync']);
        $movie = Catalog::draftMovie('Big Buck Bunny', 10378);
        $video = SampleVideos::video('1280x720', audio: true, seconds: 3);

        // 1. L'administrateur envoie la vidéo : le worker (ici synchrone) la convertit.
        $this->actingAs($this->admin);
        $upload = $this->postJson(route('admin.media.uploads.start'), [
            'kind' => 'title', 'target_id' => $movie->id()->value, 'filename' => 'film.mp4', 'size' => filesize($video), 'total_chunks' => 1,
        ])->assertCreated()->json('upload_id');
        $this->call('PUT', route('admin.media.uploads.chunk', ['upload' => $upload, 'index' => 0]), [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], (string) file_get_contents($video))->assertOk();
        $mediaId = $this->postJson(route('admin.media.uploads.complete', $upload))->assertCreated()->json('media_id');

        // 2. Le film est lisible, mais pas encore visible des visiteurs.
        expect(DB::table('media_assets')->where('id', $mediaId)->value('status'))->toBe('ready')
            ->and((bool) DB::table('titles')->where('id', $movie->id()->value)->value('is_playable'))->toBeTrue();
        auth()->logout();
        $this->get(route('titles.show', $movie->slug()->value))->assertNotFound();

        // 3. L'administrateur le publie : la publication, refusée jusque-là, passe.
        $this->actingAs($this->admin)->post(route('admin.titles.publish', $movie->id()->value))->assertSessionHas('status', 'Titre publié.');
        auth()->logout();

        // 4. Un visiteur voit le lecteur, branché sur le flux.
        $response = $this->get(route('titles.show', $movie->slug()->value))->assertOk();
        $response->assertSee('data-hls="/hls/'.$mediaId.'/master.m3u8"', false)
            ->assertSee('data-player-video', false)
            ->assertSee('preload="none"', false)
            ->assertSee('player', false);
        expect(is_file($this->root.'/hls/'.$mediaId.'/master.m3u8'))->toBeTrue();
    });
});

describe('player on the title page', function () {
    it('shows the player of a published movie whose media is ready, and nothing autoplays', function () {
        $movie = Catalog::publishedMovie('Big Buck Bunny', 10378);
        $asset = readyMedia(titleRef($movie->id()->value));

        $html = $this->get(route('titles.show', $movie->slug()->value))->assertOk()->getContent();

        expect($html)->toContain('data-hls="/hls/'.$asset->id()->value.'/master.m3u8"')
            ->and($html)->toContain('<video data-player-video controls playsinline preload="none"')
            ->and($html)->not->toContain('autoplay')
            ->and($html)->toContain('aria-label="Lecteur vidéo : Big Buck Bunny"')
            ->and($html)->toContain('Lecture');
    });

    it('shows no player while the media is not ready', function (string $status) {
        $movie = Catalog::publishedMovie('Big Buck Bunny', 10378);
        $id = M::assetId();
        // Le média porte un chemin de flux valide (par exemple celui d'un essai précédent) : seul son état doit l'écarter.
        DB::table('media_assets')->insert([
            'id' => $id->value, 'title_id' => $movie->id()->value, 'source_path' => 'incoming/x/source.mp4', 'status' => $status,
            'hls_master_path' => 'hls/'.$id->value.'/master.m3u8',
            'error_message' => $status === 'failed' ? 'échec' : null,
        ]);

        $html = $this->get(route('titles.show', $movie->slug()->value))->assertOk()->getContent();

        expect($html)->not->toContain('data-player-video')->and($html)->not->toContain('/hls/');
    })->with(['uploaded', 'queued', 'processing', 'failed']);

    it('shows no play button for an episode whose media is not ready, even with a stream path', function (string $status) {
        $series = Catalog::publishedSeries('Sintel Chronicles', 1399, seasons: 1, episodes: 1);
        $episode = $series->season(1)->episode(1);
        $id = M::assetId();
        DB::table('media_assets')->insert([
            'id' => $id->value, 'episode_id' => $episode->id->value, 'source_path' => 'incoming/x/source.mp4', 'status' => $status,
            'hls_master_path' => 'hls/'.$id->value.'/master.m3u8', 'error_message' => $status === 'failed' ? 'échec' : null,
        ]);

        $html = $this->get(route('titles.show', $series->slug()->value))->assertOk()->getContent();

        expect($html)->not->toContain('data-play-episode')->and($html)->not->toContain('/hls/');
    })->with(['uploaded', 'queued', 'processing', 'failed']);

    it('shows no player for a movie without any media', function () {
        $movie = Catalog::publishedMovie('Big Buck Bunny', 10378);

        expect($this->get(route('titles.show', $movie->slug()->value))->getContent())->not->toContain('data-player-video');
    });

    it('never builds a playing URL from a path that is not a transcoding result', function (string $path) {
        $movie = Catalog::publishedMovie('Big Buck Bunny', 10378);
        readyMedia(titleRef($movie->id()->value));
        DB::table('media_assets')->update(['hls_master_path' => $path]);

        $html = $this->get(route('titles.show', $movie->slug()->value))->assertOk()->getContent();

        expect($html)->not->toContain('data-player-video')->and($html)->not->toContain($path);
    })->with([
        ['../../etc/passwd'],
        ['hls/../../etc/passwd'],
        ['https://evil.example/master.m3u8'],
        ['//evil.example/master.m3u8'],
        ['hls/not-a-uuid/master.m3u8'],
        ['hls/01a1eaab-f114-7ec0-be6f-343634818f76/other.m3u8'],
        ['incoming/01a1eaab-f114-7ec0-be6f-343634818f76/source.mp4'],
    ]);

    it('shows a play button for each ready episode of a series, and none for the others', function () {
        $series = Catalog::publishedSeries('Sintel Chronicles', 1399, seasons: 1, episodes: 3);
        $first = $series->season(1)->episode(1);
        $third = $series->season(1)->episode(3);
        $a = readyMedia(PlayableRef::episode(PlayableId::fromString($first->id->value)));
        $b = readyMedia(PlayableRef::episode(PlayableId::fromString($third->id->value)));

        $html = $this->get(route('titles.show', $series->slug()->value))->assertOk()->getContent();

        expect(substr_count($html, 'data-play-episode'))->toBe(2)
            ->and($html)->toContain('data-hls="/hls/'.$a->id()->value.'/master.m3u8"')
            ->and($html)->toContain('data-hls="/hls/'.$b->id()->value.'/master.m3u8"')
            ->and($html)->toContain('Choisissez un épisode ci-dessous')
            ->and($html)->toContain('data-player-video')
            ->and($html)->not->toContain('data-hls="/hls/'.$a->id()->value.'/master.m3u8" class="aspect-video');
    });

    it('starts a series with an empty, hidden player', function () {
        $series = Catalog::publishedSeries('Sintel Chronicles', 1399);
        readyMedia(PlayableRef::episode(PlayableId::fromString($series->season(1)->episode(1)->id->value)));

        $html = $this->get(route('titles.show', $series->slug()->value))->getContent();

        expect($html)->toMatch('#<video data-player-video controls playsinline preload="none"\s+hidden#');
    });

    it('escapes the episode name used by the play button', function () {
        $series = Catalog::publishedSeries('Sintel Chronicles', 1399, seasons: 1, episodes: 1);
        $episode = $series->season(1)->episode(1);
        DB::table('episodes')->where('id', $episode->id->value)->update(['name' => '"><script>alert(1)</script>']);
        readyMedia(PlayableRef::episode(PlayableId::fromString($episode->id->value)));

        $html = $this->get(route('titles.show', $series->slug()->value))->getContent();

        expect($html)->not->toContain('<script>alert(1)</script>')->and($html)->toContain('&quot;&gt;&lt;script&gt;');
    });

    it('does not expose a stream for a title that is not published', function () {
        $movie = Catalog::draftMovie('Brouillon', 1);
        readyMedia(titleRef($movie->id()->value));

        $this->get(route('titles.show', $movie->slug()->value))->assertNotFound();
    });

    // Un test par page : l'objet Vite de Laravel garde en mémoire les ressources déjà demandées pendant la durée du
    // processus (en production, chaque requête repart de zéro), donc plusieurs pages dans un même test se contamineraient.
    it('loads the player script only on a page that has a stream', function (string $page, bool $expected) {
        $directory = public_path('test-build-'.bin2hex(random_bytes(3)));
        mkdir($directory.'/assets', 0755, true);
        file_put_contents($directory.'/manifest.json', json_encode([
            'resources/css/app.css' => ['file' => 'assets/app-test.css', 'src' => 'resources/css/app.css', 'isEntry' => true],
            'resources/js/app.js' => ['file' => 'assets/app-test.js', 'src' => 'resources/js/app.js', 'isEntry' => true],
            'resources/js/player.js' => ['file' => 'assets/player-test.js', 'src' => 'resources/js/player.js', 'isEntry' => true],
        ]));

        try {
            $this->withVite();
            Vite::useBuildDirectory(basename($directory));

            $with = Catalog::publishedMovie('Avec flux', 1);
            $without = Catalog::publishedMovie('Sans flux', 2);
            readyMedia(titleRef($with->id()->value));

            $uri = match ($page) {
                'with stream' => route('titles.show', $with->slug()->value),
                'without stream' => route('titles.show', $without->slug()->value),
                default => '/',
            };
            $html = $this->get($uri)->assertOk()->getContent();

            expect(str_contains($html, 'assets/player-test.js'))->toBe($expected)
                ->and($html)->toContain('assets/app-test.js');
        } finally {
            (new Process(['rm', '-rf', $directory]))->run();
        }
    })->with([
        'a title with a stream' => ['with stream', true],
        'a title without stream' => ['without stream', false],
        'the home page' => ['home', false],
    ]);
});

describe('nginx serves the streams', function () {
    $configs = [
        'dev' => __DIR__.'/../../../docker/nginx/default.conf',
        'prod' => __DIR__.'/../../../docker/nginx/prod.conf.template',
    ];

    it('exposes /hls/ with the right types and no directory listing', function (string $file) {
        $config = (string) file_get_contents($file);

        expect($config)->toContain('location /hls/')
            ->and($config)->toContain('autoindex off')
            ->and($config)->toContain('application/vnd.apple.mpegurl m3u8')
            ->and($config)->toContain('video/mp2t ts')
            ->and($config)->toContain('Cache-Control "no-cache"');
    })->with($configs);

    it('only serves the HLS folder, never the whole media root', function () {
        $config = (string) file_get_contents(__DIR__.'/../../../docker/nginx/prod.conf.template');

        expect($config)->toContain('alias /srv/media/hls/;')->and($config)->not->toContain('alias /srv/media/;')
            ->and($config)->not->toContain('incoming');
    });

    it('lets the browser keep the segments for a long time and never the playlists', function () {
        $config = (string) file_get_contents(__DIR__.'/../../../docker/nginx/prod.conf.template');

        expect($config)->toMatch('#\\\\\.m3u8\$.*?no-cache#s')->and($config)->toMatch('#\(ts\|m4s\|mp4\)\$.*?immutable#s');
    });
});
