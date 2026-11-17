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
use HomeFlix\Media\UI\Livewire\MediaList;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\Support\CatalogFixtures as Catalog;
use Tests\Support\MediaFixtures as F;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/homeflix-admin-'.bin2hex(random_bytes(4));
    mkdir($this->root.'/incoming/x', 0755, true);
    config(['media.root' => $this->root]);
    $this->admin = User::factory()->admin()->create();
});

afterEach(function () {
    (new Process(['rm', '-rf', $this->root]))->run();
});

/** Un média pour un nouveau film, avec son fichier source sur le disque. */
function trackedMedia(string $name, MediaStatus $status, ?string $error = null): MediaAsset
{
    static $n = 500;
    $n++;
    $movie = Catalog::movie(tmdbId: $n, name: $name);
    app(TitleRepository::class)->save($movie);

    $asset = MediaAsset::reconstitute(
        F::assetId(), PlayableRef::title(PlayableId::fromString($movie->id()->value)), new RelativePath('incoming/x/source.mp4'), $status,
        $status === MediaStatus::Ready ? new Duration(60) : null,
        $status === MediaStatus::Failed ? ($error ?? 'ffmpeg a échoué') : null,
        $status === MediaStatus::Ready ? new RelativePath('hls/x/master.m3u8') : null,
        $status === MediaStatus::Ready ? [F::rendition()] : [],
    );
    app(MediaAssetRepository::class)->save($asset);
    file_put_contents(test()->root.'/incoming/x/source.mp4', 'x');

    return $asset;
}

describe('access', function () {
    it('sends a visitor to the login page', function () {
        $this->get('/admin/media')->assertRedirect('/login');
    });

    it('refuses a simple user', function () {
        $this->actingAs(User::factory()->create())->get('/admin/media')->assertForbidden();
    });

    it('refuses the component itself to a simple user and to a visitor', function () {
        Livewire::actingAs(User::factory()->create())->test(MediaList::class)->assertForbidden();
        Livewire::test(MediaList::class)->assertForbidden();
    });

    it('shows the link to administrators only', function () {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee(route('admin.media.index'), false);
        $this->actingAs($this->admin)->get('/dashboard')->assertSee(route('admin.media.index'), false);
    });
});

describe('list', function () {
    it('says when there is no video', function () {
        $this->actingAs($this->admin)->get('/admin/media')->assertOk()->assertSee('Aucune vidéo envoyée');
    });

    it('shows the state of every video', function () {
        trackedMedia('Film prêt', MediaStatus::Ready);
        trackedMedia('Film en file', MediaStatus::Queued);
        trackedMedia('Film en cours', MediaStatus::Processing);
        trackedMedia('Film en échec', MediaStatus::Failed, 'Le fichier n\'est pas une vidéo lisible.');

        $this->actingAs($this->admin)->get('/admin/media')
            ->assertOk()
            ->assertSee('Film prêt')->assertSee('Prête')
            ->assertSee('En attente')
            ->assertSee('Conversion en cours')
            ->assertSee('Échec')->assertSee('Le fichier n\'est pas une vidéo lisible.')
            ->assertSee('720p');
    });

    it('links each video to its upload page', function () {
        $asset = trackedMedia('Film', MediaStatus::Ready);

        $this->actingAs($this->admin)->get('/admin/media')
            ->assertSee(route('admin.media.show', ['kind' => 'title', 'target' => $asset->target()->id->value]), false);
    });

    it('escapes the titles and the error messages', function () {
        trackedMedia('<script>alert(1)</script>', MediaStatus::Failed, '<img src=x onerror=alert(1)>');

        $this->actingAs($this->admin)->get('/admin/media')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
    });
});

describe('refresh by polling', function () {
    it('polls every 10 seconds while a conversion is under way', function (MediaStatus $status) {
        trackedMedia('Film', $status);

        Livewire::actingAs($this->admin)->test(MediaList::class)->assertSeeHtml('wire:poll.10s');
    })->with([MediaStatus::Uploaded, MediaStatus::Queued, MediaStatus::Processing]);

    it('stops polling when everything is finished', function () {
        trackedMedia('Prêt', MediaStatus::Ready);
        trackedMedia('En échec', MediaStatus::Failed);

        Livewire::actingAs($this->admin)->test(MediaList::class)->assertDontSeeHtml('wire:poll');
    });

    it('does not poll an empty list', function () {
        Livewire::actingAs($this->admin)->test(MediaList::class)->assertDontSeeHtml('wire:poll');
    });

    it('shows the new state when the list is refreshed', function () {
        $asset = trackedMedia('Film', MediaStatus::Queued);
        $component = Livewire::actingAs($this->admin)->test(MediaList::class)->assertSee('En attente')->assertDontSee('Prête');

        DB::table('media_assets')->where('id', $asset->id()->value)->update(['status' => 'ready', 'duration_seconds' => 60, 'hls_master_path' => 'hls/x/master.m3u8']);

        $component->call('$refresh')->assertSee('Prête')->assertDontSee('En attente')->assertDontSeeHtml('wire:poll');
    });

    it('uses plain HTTP polling, no WebSocket', function () {
        trackedMedia('Film', MediaStatus::Queued);

        $html = $this->actingAs($this->admin)->get('/admin/media')->getContent();

        expect(in_array(config('broadcasting.default'), [null, 'null'], true))->toBeTrue()
            ->and(strtolower($html))->not->toContain('pusher')->and(strtolower($html))->not->toContain('websocket')->and($html)->not->toContain('Echo');
    });
});

describe('retry', function () {
    it('offers to retry a failed video only', function () {
        trackedMedia('Film en échec', MediaStatus::Failed);
        trackedMedia('Film prêt', MediaStatus::Ready);
        trackedMedia('Film en file', MediaStatus::Queued);

        $html = $this->actingAs($this->admin)->get('/admin/media')->getContent();

        expect(substr_count($html, 'wire:click="retry('))->toBe(1);
    });

    it('puts a failed video back in the queue', function () {
        Queue::fake();
        $asset = trackedMedia('Film', MediaStatus::Failed);

        Livewire::actingAs($this->admin)->test(MediaList::class)
            ->call('retry', $asset->id()->value)
            ->assertSee('La vidéo est remise en file de conversion.')
            ->assertSee('En attente')
            ->assertDontSeeHtml('wire:click="retry(');

        expect(DB::table('media_assets')->where('id', $asset->id()->value)->value('status'))->toBe('queued')
            ->and(DB::table('media_assets')->where('id', $asset->id()->value)->value('error_message'))->toBeNull();
        Queue::assertPushedOn('transcoding', TranscodeMediaJob::class, fn (TranscodeMediaJob $job) => $job->mediaAssetId === $asset->id()->value);
    });

    it('refuses to retry a video that did not fail', function (MediaStatus $status) {
        Queue::fake();
        $asset = trackedMedia('Film', $status);

        Livewire::actingAs($this->admin)->test(MediaList::class)
            ->call('retry', $asset->id()->value)
            ->assertSee('Seule une vidéo en échec peut être relancée.');

        Queue::assertNothingPushed();
        expect(DB::table('media_assets')->where('id', $asset->id()->value)->value('status'))->toBe($status->value);
    })->with([MediaStatus::Queued, MediaStatus::Processing, MediaStatus::Ready]);

    it('explains when the source file is gone', function () {
        Queue::fake();
        $asset = trackedMedia('Film', MediaStatus::Failed);
        unlink($this->root.'/incoming/x/source.mp4');

        Livewire::actingAs($this->admin)->test(MediaList::class)
            ->call('retry', $asset->id()->value)
            ->assertSee('Le fichier source n\'existe plus : renvoyez la vidéo.');

        Queue::assertNothingPushed();
        expect(DB::table('media_assets')->where('id', $asset->id()->value)->value('status'))->toBe('failed');
    });

    it('does not crash on an unknown or invalid identifier', function (string $id) {
        Livewire::actingAs($this->admin)->test(MediaList::class)->call('retry', $id)->assertOk()->assertSet('failure', fn (string $failure) => $failure !== '');
    })->with(['01900000-0000-7000-8000-000000000000', 'not-a-uuid', '../../etc', '']);

    it('cannot be triggered by someone who is not an administrator', function () {
        Queue::fake();
        $asset = trackedMedia('Film', MediaStatus::Failed);
        $component = Livewire::actingAs($this->admin)->test(MediaList::class);

        // La session change entre l'affichage et l'appel : chaque méthode vérifie elle-même le droit.
        $this->actingAs(User::factory()->create());
        $component->call('retry', $asset->id()->value)->assertForbidden();

        Queue::assertNothingPushed();
        expect(DB::table('media_assets')->where('id', $asset->id()->value)->value('status'))->toBe('failed');
    });
});
