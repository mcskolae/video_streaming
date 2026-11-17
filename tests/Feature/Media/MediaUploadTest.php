<?php

declare(strict_types=1);

use App\Models\User;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Media\Domain\Model\Duration;
use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableId;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Domain\Model\RelativePath;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use HomeFlix\Media\Infrastructure\Queue\TranscodeMediaJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\Process;
use Tests\Support\CatalogFixtures as Catalog;
use Tests\Support\Media\SampleVideos;
use Tests\Support\MediaFixtures;

const CHUNK_BYTES = 5 * 1024 * 1024;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/homeflix-http-'.bin2hex(random_bytes(4));
    mkdir($this->root, 0755, true);
    config(['media.root' => $this->root]);

    $this->admin = User::factory()->admin()->create();
    $this->movie = Catalog::movie();
    app(TitleRepository::class)->save($this->movie);
    $this->movieId = $this->movie->id()->value;
});

afterEach(function () {
    (new Process(['rm', '-rf', $this->root]))->run();
});

/** @return array{string, int} identifiant d'envoi et nombre de morceaux */
function startUpload(string $targetId, int $size, string $filename = 'film.mp4', string $kind = 'title'): array
{
    $response = test()->actingAs(test()->admin)->postJson(route('admin.media.uploads.start'), [
        'kind' => $kind, 'target_id' => $targetId, 'filename' => $filename, 'size' => $size, 'total_chunks' => (int) ceil($size / CHUNK_BYTES),
    ])->assertCreated();

    return [$response->json('upload_id'), $response->json('total_chunks')];
}

/** @return TestResponse<JsonResponse> */
function putChunk(string $uploadId, int $index, string $bytes): TestResponse
{
    return test()->actingAs(test()->admin)->call('PUT', route('admin.media.uploads.chunk', ['upload' => $uploadId, 'index' => $index]), [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $bytes);
}

/** Envoie un fichier du disque par morceaux, comme le script de la page. */
function sendFile(string $uploadId, string $path): void
{
    $handle = fopen($path, 'rb');

    for ($index = 0; ! feof($handle); $index++) {
        $bytes = (string) fread($handle, CHUNK_BYTES);

        if ($bytes === '') {
            break;
        }

        putChunk($uploadId, $index, $bytes)->assertOk();
    }

    fclose($handle);
}

/** @return TestResponse<JsonResponse> */
function completeUpload(string $uploadId): TestResponse
{
    return test()->actingAs(test()->admin)->postJson(route('admin.media.uploads.complete', $uploadId));
}

describe('access', function () {
    $json = [
        'start' => ['postJson', '/admin/media/uploads'],
        'status' => ['getJson', '/admin/media/uploads/01900000-0000-7000-8000-000000000000'],
        'chunk' => ['putJson', '/admin/media/uploads/01900000-0000-7000-8000-000000000000/chunks/0'],
        'complete' => ['postJson', '/admin/media/uploads/01900000-0000-7000-8000-000000000000/complete'],
    ];

    it('refuses a visitor on every route', function (string $method, string $uri) {
        $this->{$method}($uri)->assertUnauthorized();
    })->with($json);

    it('refuses a simple user with a 403 on every route', function (string $method, string $uri) {
        $this->actingAs(User::factory()->create())->{$method}($uri)->assertForbidden();
    })->with($json);

    it('sends a visitor to the login page for the upload page', function () {
        $this->get("/admin/media/title/{$this->movieId}")->assertRedirect('/login');
    });

    it('refuses a simple user on the upload page', function () {
        $this->actingAs(User::factory()->create())->get("/admin/media/title/{$this->movieId}")->assertForbidden();
    });

    it('protects every route under /admin/media with auth, admin and the web group', function () {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'admin/media'));

        expect($routes)->toHaveCount(6);

        foreach ($routes as $route) {
            expect($route->gatherMiddleware())->toContain('auth', 'admin', 'web');
        }
    });
});

describe('upload page', function () {
    it('invites to send a video when there is none', function () {
        $this->actingAs($this->admin)->get("/admin/media/title/{$this->movieId}")
            ->assertOk()
            ->assertSee('Aucune vidéo n\'a encore été envoyée')
            ->assertSee('Envoyer une vidéo')
            ->assertSee('data-upload-form', false)
            ->assertSee(route('admin.media.uploads.start'), false)
            ->assertSee('Big Buck Bunny');
    });

    it('shows the state of a video, and no upload form while it is being handled', function (MediaStatus $status, string $label) {
        $asset = MediaAsset::reconstitute(
            MediaFixtures::assetId(), PlayableRef::title(PlayableId::fromString($this->movieId)),
            new RelativePath('incoming/a/source.mp4'), $status,
            $status === MediaStatus::Ready ? new Duration(754) : null,
            $status === MediaStatus::Failed ? 'ffmpeg a échoué' : null,
            $status === MediaStatus::Ready ? new RelativePath('hls/a/master.m3u8') : null,
            $status === MediaStatus::Ready ? [MediaFixtures::rendition()] : [],
        );
        app(MediaAssetRepository::class)->save($asset);

        $response = $this->actingAs($this->admin)->get("/admin/media/title/{$this->movieId}")->assertOk()->assertSee($label);

        $status === MediaStatus::Failed ? $response->assertSee('data-upload-form', false)->assertSee('ffmpeg a échoué') : $response->assertDontSee('data-upload-form', false);
    })->with([
        'queued' => [MediaStatus::Queued, 'En attente de conversion'],
        'processing' => [MediaStatus::Processing, 'Conversion en cours'],
        'ready' => [MediaStatus::Ready, 'Prête'],
        'failed' => [MediaStatus::Failed, 'Échec'],
    ]);

    it('is not found for an unknown target, a wrong kind or an invalid identifier', function (string $uri) {
        $this->actingAs($this->admin)->get($uri)->assertNotFound();
    })->with([
        ['/admin/media/title/01900000-0000-7000-8000-000000000000'],
        ['/admin/media/episode/01900000-0000-7000-8000-000000000000'],
        ['/admin/media/series/01900000-0000-7000-8000-000000000000'],
        ['/admin/media/title/not-a-uuid'],
    ]);

    it('escapes the title', function () {
        DB::table('titles')->update(['title' => '<script>alert(1)</script>']);

        $this->actingAs($this->admin)->get("/admin/media/title/{$this->movieId}")
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    });

    it('is linked from the catalog list for movies', function () {
        $this->actingAs($this->admin)->get('/admin/titles')->assertSee(route('admin.media.show', ['kind' => 'title', 'target' => $this->movieId]), false);
    });
});

describe('starting an upload', function () {
    it('opens an upload', function () {
        $this->actingAs($this->admin)->postJson(route('admin.media.uploads.start'), [
            'kind' => 'title', 'target_id' => $this->movieId, 'filename' => 'film.mp4', 'size' => 12 * 1024 * 1024, 'total_chunks' => 3,
        ])->assertCreated()->assertJson(['chunk_size' => CHUNK_BYTES, 'total_chunks' => 3])->assertJsonStructure(['upload_id']);
    });

    it('refuses invalid input', function (array $payload, string $field) {
        $payload = array_merge(['kind' => 'title', 'target_id' => $this->movieId, 'filename' => 'a.mp4', 'size' => 1000, 'total_chunks' => 1], $payload);

        $this->actingAs($this->admin)->postJson(route('admin.media.uploads.start'), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'no kind' => [['kind' => null], 'kind'],
        'unknown kind' => [['kind' => 'series'], 'kind'],
        'target not a uuid' => [['target_id' => '../../etc'], 'target_id'],
        'no filename' => [['filename' => null], 'filename'],
        'zero size' => [['size' => 0], 'size'],
        'negative size' => [['size' => -1], 'size'],
        'size not a number' => [['size' => 'big'], 'size'],
        'zero chunks' => [['total_chunks' => 0], 'total_chunks'],
        'filename array' => [['filename' => ['a.mp4']], 'filename'],
    ]);

    it('explains a refusal in plain words', function (array $payload, string $message) {
        $payload = array_merge(['kind' => 'title', 'target_id' => $this->movieId, 'filename' => 'a.mp4', 'size' => 1000, 'total_chunks' => 1], $payload);

        $this->actingAs($this->admin)->postJson(route('admin.media.uploads.start'), $payload)->assertUnprocessable()->assertJson(['message' => $message]);
    })->with([
        'format' => [['filename' => 'virus.exe'], 'Format non accepté. Formats acceptés : mp4, m4v, mov, mkv, webm, avi.'],
        'double extension' => [['filename' => 'a.mp4.php'], 'Format non accepté. Formats acceptés : mp4, m4v, mov, mkv, webm, avi.'],
        'chunks' => [['total_chunks' => 5], 'Le découpage annoncé ne correspond pas à la taille du fichier.'],
        'unknown movie' => [['target_id' => '01900000-0000-7000-8000-000000000000'], 'Ce film ou cet épisode n\'existe pas.'],
    ]);

    it('refuses a file that is too big', function () {
        config(['media.max_upload_mb' => 1]);

        $this->actingAs($this->admin)->postJson(route('admin.media.uploads.start'), [
            'kind' => 'title', 'target_id' => $this->movieId, 'filename' => 'a.mp4', 'size' => 2 * 1024 * 1024, 'total_chunks' => 1,
        ])->assertUnprocessable()->assertJson(['message' => 'Le fichier dépasse la taille maximale (1 Mo).']);
    });

    it('refuses a second video for a movie', function () {
        app(MediaAssetRepository::class)->save(MediaAsset::upload(
            MediaFixtures::assetId(), PlayableRef::title(PlayableId::fromString($this->movieId)), new RelativePath('incoming/a/source.mp4'),
        ));

        $this->actingAs($this->admin)->postJson(route('admin.media.uploads.start'), [
            'kind' => 'title', 'target_id' => $this->movieId, 'filename' => 'a.mp4', 'size' => 1000, 'total_chunks' => 1,
        ])->assertUnprocessable()->assertJson(['message' => 'Une vidéo existe déjà pour ce titre. Seule une vidéo en échec peut être remplacée.']);
    });

    it('never puts the announced file name on the disk', function () {
        [$uploadId] = startUpload($this->movieId, 1000, '../../../etc/cron.d/évil; rm -rf.MP4');

        $names = array_map('basename', glob($this->root.'/incoming/_uploads/'.$uploadId.'/*') ?: []);

        expect($names)->toBe(['meta.json'])
            ->and(file_get_contents($this->root.'/incoming/_uploads/'.$uploadId.'/meta.json'))->not->toContain('cron')->and(file_exists('/etc/cron.d/évil'))->toBeFalse();
    });
});

describe('chunks', function () {
    it('receives a chunk and reports what has arrived', function () {
        [$uploadId] = startUpload($this->movieId, 12 * 1024 * 1024);

        putChunk($uploadId, 0, str_repeat('a', CHUNK_BYTES))->assertOk()->assertJson(['received' => 0, 'size' => CHUNK_BYTES]);
        putChunk($uploadId, 2, str_repeat('c', 2 * 1024 * 1024))->assertOk();

        $this->actingAs($this->admin)->getJson(route('admin.media.uploads.status', $uploadId))
            ->assertOk()->assertExactJson(['received' => [0, 2], 'total_chunks' => 3]);
    });

    it('accepts the same chunk twice', function () {
        [$uploadId] = startUpload($this->movieId, 12 * 1024 * 1024);

        putChunk($uploadId, 1, str_repeat('a', CHUNK_BYTES))->assertOk();
        putChunk($uploadId, 1, str_repeat('b', CHUNK_BYTES))->assertOk();

        expect(file_get_contents($this->root.'/incoming/_uploads/'.$uploadId.'/chunk_1'))->toBe(str_repeat('b', CHUNK_BYTES));
    });

    it('refuses a chunk of the wrong size without keeping it', function (int $index, int $bytes) {
        [$uploadId] = startUpload($this->movieId, 12 * 1024 * 1024);

        putChunk($uploadId, $index, str_repeat('x', $bytes))->assertUnprocessable()->assertJson(['message' => 'Morceau invalide : numéro ou taille incorrects.']);

        expect(glob($this->root.'/incoming/_uploads/'.$uploadId.'/chunk_*'))->toBe([]);
    })->with(['too small' => [0, 100], 'too big' => [0, CHUNK_BYTES + 1], 'last too big' => [2, 3 * 1024 * 1024], 'empty' => [1, 0]]);

    it('refuses a chunk number outside the file', function () {
        [$uploadId] = startUpload($this->movieId, 12 * 1024 * 1024);

        putChunk($uploadId, 3, str_repeat('x', 10))->assertUnprocessable();
    });

    it('refuses a chunk number that is not a number', function () {
        [$uploadId] = startUpload($this->movieId, 12 * 1024 * 1024);

        $this->actingAs($this->admin)->call('PUT', "/admin/media/uploads/{$uploadId}/chunks/abc", [], [], [], [], 'x')->assertNotFound();
    });

    it('refuses a chunk for an unknown upload', function () {
        putChunk('01900000-0000-7000-8000-000000000000', 0, 'x')->assertUnprocessable()->assertJson(['message' => 'Envoi inconnu ou expiré. Recommencez l\'envoi.']);
    });

    it('does not accept an identifier that is not a UUID, so it can never become a path', function () {
        $this->actingAs($this->admin)->call('PUT', '/admin/media/uploads/..%2F..%2Fetc/chunks/0', [], [], [], [], 'x')->assertNotFound();
        $this->actingAs($this->admin)->getJson('/admin/media/uploads/not-a-uuid')->assertNotFound();
    });

    it('says an upload is unknown', function () {
        $this->actingAs($this->admin)->getJson(route('admin.media.uploads.status', '01900000-0000-7000-8000-000000000000'))
            ->assertNotFound()->assertJson(['message' => 'Envoi inconnu ou expiré. Recommencez l\'envoi.']);
    });
});

describe('completing', function () {
    it('assembles a real multi-chunk video, creates the media and queues the conversion', function () {
        if (! SampleVideos::ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg et ffprobe ne sont pas installés.');
        }

        Queue::fake();
        $video = SampleVideos::large();
        [$uploadId, $chunks] = startUpload($this->movieId, filesize($video));
        sendFile($uploadId, $video);

        $response = completeUpload($uploadId)->assertCreated();

        $mediaId = $response->json('media_id');
        expect($chunks)->toBeGreaterThanOrEqual(3)
            ->and(DB::table('media_assets')->where('id', $mediaId)->value('status'))->toBe('queued')
            ->and(DB::table('media_assets')->where('id', $mediaId)->value('title_id'))->toBe($this->movieId)
            ->and(sha1_file($this->root.'/incoming/'.$mediaId.'/source.mp4'))->toBe(sha1_file($video))
            ->and(is_dir($this->root.'/incoming/_uploads/'.$uploadId))->toBeFalse();
        Queue::assertPushedOn('transcoding', TranscodeMediaJob::class, fn (TranscodeMediaJob $job) => $job->mediaAssetId === $mediaId);
    });

    it('goes all the way to a playable stream when the worker runs', function () {
        if (! SampleVideos::ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg et ffprobe ne sont pas installés.');
        }

        config(['queue.default' => 'sync']);
        $video = SampleVideos::video('1280x720', audio: true, seconds: 3);
        [$uploadId] = startUpload($this->movieId, filesize($video));
        sendFile($uploadId, $video);

        $mediaId = completeUpload($uploadId)->assertCreated()->json('media_id');

        expect(DB::table('media_assets')->where('id', $mediaId)->value('status'))->toBe('ready')
            ->and(is_file($this->root.'/hls/'.$mediaId.'/master.m3u8'))->toBeTrue();
    });

    it('refuses to assemble while chunks are missing', function () {
        [$uploadId] = startUpload($this->movieId, 12 * 1024 * 1024);
        putChunk($uploadId, 0, str_repeat('a', CHUNK_BYTES))->assertOk();

        completeUpload($uploadId)->assertUnprocessable()->assertJson(['message' => 'Tous les morceaux ne sont pas arrivés.']);

        expect(DB::table('media_assets')->count())->toBe(0);
    });

    it('refuses what is not a video, whatever its name says, and cleans up', function (string $content) {
        Queue::fake();
        [$uploadId] = startUpload($this->movieId, strlen($content), 'film.mp4');
        putChunk($uploadId, 0, $content)->assertOk();

        completeUpload($uploadId)->assertUnprocessable()->assertJson(['message' => 'Le fichier n\'est pas une vidéo valide.']);

        expect(DB::table('media_assets')->count())->toBe(0)
            ->and(is_dir($this->root.'/incoming/_uploads/'.$uploadId))->toBeFalse()
            ->and(glob($this->root.'/incoming/*/source.*') ?: [])->toBe([]);
        Queue::assertNothingPushed();
    })->with([
        'php script' => ['<?php system($_GET["c"]); ?>'],
        'html' => ['<html><script>alert(1)</script></html>'],
        'plain text' => [str_repeat("Ceci n'est pas une vidéo.\n", 50)],
        'random bytes' => [random_bytes(4096)],
        'windows executable' => ["MZ\x90\x00\x03\x00\x00\x00".str_repeat("\0", 200)],
    ]);

    it('refuses a real video sent under a name with another format', function () {
        if (! SampleVideos::ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg et ffprobe ne sont pas installés.');
        }

        $audio = SampleVideos::audioOnly();
        [$uploadId] = startUpload($this->movieId, filesize($audio), 'film.mp4');
        sendFile($uploadId, $audio);

        completeUpload($uploadId)->assertUnprocessable();

        expect(DB::table('media_assets')->count())->toBe(0);
    });

    it('cannot be completed twice', function () {
        if (! SampleVideos::ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg et ffprobe ne sont pas installés.');
        }

        Queue::fake();
        $video = SampleVideos::video('640x360', audio: false, seconds: 2);
        [$uploadId] = startUpload($this->movieId, filesize($video));
        sendFile($uploadId, $video);

        completeUpload($uploadId)->assertCreated();
        completeUpload($uploadId)->assertUnprocessable()->assertJson(['message' => 'Envoi inconnu ou expiré. Recommencez l\'envoi.']);

        expect(DB::table('media_assets')->count())->toBe(1);
        Queue::assertPushed(TranscodeMediaJob::class, 1);
    });

    it('replaces a failed media', function () {
        if (! SampleVideos::ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg et ffprobe ne sont pas installés.');
        }

        Queue::fake();
        $failed = MediaAsset::upload(MediaFixtures::assetId(), PlayableRef::title(PlayableId::fromString($this->movieId)), new RelativePath('incoming/old/source.mp4'));
        $failed->markFailed('échec', MediaFixtures::now());
        app(MediaAssetRepository::class)->save($failed);
        $video = SampleVideos::video('640x360', audio: false, seconds: 2);

        [$uploadId] = startUpload($this->movieId, filesize($video));
        sendFile($uploadId, $video);
        $mediaId = completeUpload($uploadId)->assertCreated()->json('media_id');

        expect(DB::table('media_assets')->count())->toBe(1)
            ->and(DB::table('media_assets')->value('id'))->toBe($mediaId)
            ->and(DB::table('media_assets')->value('status'))->toBe('queued');
    });
});
