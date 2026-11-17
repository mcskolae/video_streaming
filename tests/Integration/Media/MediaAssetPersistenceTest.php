<?php

declare(strict_types=1);

use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Media\Domain\Model\Duration;
use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableId;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Domain\Model\RelativePath;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CatalogFixtures as Catalog;
use Tests\Support\MediaFixtures as F;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(MediaAssetRepository::class);
});

/** Enregistre un film en base et retourne la cible d'un média. */
function movieTarget(): PlayableRef
{
    $n = random_int(1, 1_000_000);
    $movie = Catalog::movie(tmdbId: $n, name: "Film {$n}");
    app(TitleRepository::class)->save($movie);

    return PlayableRef::title(PlayableId::fromString($movie->id()->value));
}

/** Enregistre une série et retourne la cible de son premier épisode. */
function episodeTarget(): PlayableRef
{
    $n = random_int(1, 1_000_000);
    $series = Catalog::series(tmdbId: $n, name: "Série {$n}");
    app(TitleRepository::class)->save($series);

    return PlayableRef::episode(PlayableId::fromString($series->season(1)->episode(1)->id->value));
}

/** @param  Closure(): mixed  $statement */
function attemptMedia(Closure $statement): void
{
    DB::transaction($statement);
}

describe('repository', function () {
    it('saves and reloads a media for a movie', function () {
        $asset = F::uploaded(movieTarget());
        $this->repository->save($asset);

        $loaded = $this->repository->find($asset->id());

        expect($loaded)->not->toBeNull()
            ->and($loaded->status())->toBe(MediaStatus::Uploaded)
            ->and($loaded->target()->equals($asset->target()))->toBeTrue()
            ->and($loaded->sourcePath()->value)->toBe('incoming/abc/source.mp4')
            ->and($loaded->renditions())->toBe([]);
    });

    it('saves and reloads a ready media with its renditions', function () {
        $asset = F::uploaded(episodeTarget());
        $asset->queue();
        $asset->startProcessing();
        $asset->markReady(new Duration(754), new RelativePath('hls/abc/master.m3u8'), [F::rendition('1080p', 1920, 1080), F::rendition('720p')], F::now());
        $this->repository->save($asset);

        $loaded = $this->repository->find($asset->id());

        expect($loaded->status())->toBe(MediaStatus::Ready)
            ->and($loaded->duration()?->seconds)->toBe(754)
            ->and($loaded->hlsMasterPath()?->value)->toBe('hls/abc/master.m3u8')
            ->and(array_map(fn ($r) => [$r->label, $r->height, $r->videoBitrateKbps], $loaded->renditions()))->toBe([['720p', 720, 2500], ['1080p', 1080, 2500]])
            ->and($loaded->target()->kind->value)->toBe('episode');
    });

    it('persists a failure with its message, then a retry that clears it', function () {
        $asset = F::uploaded(movieTarget());
        $asset->markFailed('ffmpeg a échoué', F::now());
        $this->repository->save($asset);

        $failed = $this->repository->find($asset->id());
        expect($failed->status())->toBe(MediaStatus::Failed)->and($failed->errorMessage())->toBe('ffmpeg a échoué');

        $failed->retry();
        $this->repository->save($failed);

        expect($this->repository->find($asset->id())->errorMessage())->toBeNull()
            ->and(DB::table('media_assets')->count())->toBe(1);
    });

    it('finds the media of a movie or of an episode', function () {
        $movie = movieTarget();
        $episode = episodeTarget();
        $this->repository->save(F::uploaded($movie));
        $this->repository->save(F::uploaded($episode));

        expect($this->repository->findByTarget($movie)->target()->equals($movie))->toBeTrue()
            ->and($this->repository->findByTarget($episode)->target()->equals($episode))->toBeTrue()
            ->and($this->repository->findByTarget(PlayableRef::title(F::playableId())))->toBeNull();
    });

    it('returns null for an unknown media', function () {
        expect($this->repository->find(F::assetId()))->toBeNull();
    });

    it('does not mistake a movie id for an episode id', function () {
        $movie = movieTarget();
        $this->repository->save(F::uploaded($movie));

        expect($this->repository->findByTarget(PlayableRef::episode($movie->id)))->toBeNull();
    });

    it('does not duplicate renditions when saved twice', function () {
        $asset = F::inStatus(MediaStatus::Ready);
        $reloaded = MediaAsset::reconstitute(
            $asset->id(), movieTarget(), $asset->sourcePath(), MediaStatus::Ready, $asset->duration(), null, $asset->hlsMasterPath(), $asset->renditions(),
        );

        $this->repository->save($reloaded);
        $this->repository->save($reloaded);

        expect(DB::table('renditions')->count())->toBe(1);
    });

    it('stores a UUID v7 for the media and for each rendition', function () {
        $asset = F::inStatus(MediaStatus::Ready);
        $stored = MediaAsset::reconstitute($asset->id(), movieTarget(), $asset->sourcePath(), MediaStatus::Ready, $asset->duration(), null, $asset->hlsMasterPath(), $asset->renditions());
        $this->repository->save($stored);

        expect(Catalog::uuidVersion((string) DB::table('media_assets')->value('id')))->toBe(7)
            ->and(Catalog::uuidVersion((string) DB::table('renditions')->value('id')))->toBe(7);
    });
});

describe('database constraints', function () {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function mediaRow(array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid7(),
            'source_path' => 'incoming/abc/source.mp4',
            'status' => 'uploaded',
        ], $overrides);
    }

    it('refuses a media with no movie and no episode', function () {
        expect(fn () => attemptMedia(fn () => DB::table('media_assets')->insert(mediaRow())))
            ->toThrow(QueryException::class, 'media_assets_target_exactly_one');
    });

    it('refuses a media with both a movie and an episode', function () {
        $movie = movieTarget();
        $episode = episodeTarget();

        expect(fn () => attemptMedia(fn () => DB::table('media_assets')->insert(mediaRow(['title_id' => $movie->id->value, 'episode_id' => $episode->id->value]))))
            ->toThrow(QueryException::class, 'media_assets_target_exactly_one');
    });

    it('allows only one media per movie', function () {
        $movie = movieTarget();
        DB::table('media_assets')->insert(mediaRow(['title_id' => $movie->id->value]));

        expect(fn () => attemptMedia(fn () => DB::table('media_assets')->insert(mediaRow(['title_id' => $movie->id->value]))))
            ->toThrow(UniqueConstraintViolationException::class, 'media_assets_title_id_unique');
    });

    it('allows only one media per episode', function () {
        $episode = episodeTarget();
        DB::table('media_assets')->insert(mediaRow(['episode_id' => $episode->id->value]));

        expect(fn () => attemptMedia(fn () => DB::table('media_assets')->insert(mediaRow(['episode_id' => $episode->id->value]))))
            ->toThrow(UniqueConstraintViolationException::class, 'media_assets_episode_id_unique');
    });

    it('refuses a media attached to an unknown movie or episode', function (string $column, string $constraint) {
        expect(fn () => attemptMedia(fn () => DB::table('media_assets')->insert(mediaRow([$column => (string) Str::uuid7()]))))
            ->toThrow(QueryException::class, $constraint);
    })->with([['title_id', 'media_assets_title_id_foreign'], ['episode_id', 'media_assets_episode_id_foreign']]);

    it('refuses invalid states', function (array $changes, string $constraint) {
        $movie = movieTarget();

        expect(fn () => attemptMedia(fn () => DB::table('media_assets')->insert(mediaRow(array_merge(['title_id' => $movie->id->value], $changes)))))
            ->toThrow(QueryException::class, $constraint);
    })->with([
        'ready without duration' => [['status' => 'ready', 'hls_master_path' => 'hls/x/master.m3u8'], 'media_assets_ready_has_stream'],
        'ready without stream' => [['status' => 'ready', 'duration_seconds' => 60], 'media_assets_ready_has_stream'],
        'error on a queued media' => [['status' => 'queued', 'error_message' => 'oops'], 'media_assets_error_only_when_failed'],
        'failed without message' => [['status' => 'failed'], 'media_assets_failed_has_error'],
        'zero duration' => [['duration_seconds' => 0], 'media_assets_duration_range'],
        'huge duration' => [['duration_seconds' => 86401], 'media_assets_duration_range'],
        'unknown status' => [['status' => 'archived'], 'media_assets_status_check'],
    ]);

    it('accepts a ready media with its stream and a failed media with its message', function () {
        $first = movieTarget();
        $second = movieTarget();

        DB::table('media_assets')->insert(mediaRow(['title_id' => $first->id->value, 'status' => 'ready', 'duration_seconds' => 60, 'hls_master_path' => 'hls/x/master.m3u8']));
        DB::table('media_assets')->insert(mediaRow(['title_id' => $second->id->value, 'status' => 'failed', 'error_message' => 'oops']));

        expect(DB::table('media_assets')->count())->toBe(2);
    });

    it('refuses two renditions with the same label and invalid sizes', function () {
        $movie = movieTarget();
        $assetId = (string) Str::uuid7();
        DB::table('media_assets')->insert(mediaRow(['id' => $assetId, 'title_id' => $movie->id->value]));
        $row = fn (array $o = []) => array_merge([
            'id' => (string) Str::uuid7(), 'media_asset_id' => $assetId, 'label' => '720p',
            'width' => 1280, 'height' => 720, 'video_bitrate_kbps' => 2500, 'playlist_path' => 'hls/x/720p/index.m3u8',
        ], $o);
        DB::table('renditions')->insert($row());

        expect(fn () => attemptMedia(fn () => DB::table('renditions')->insert($row())))->toThrow(UniqueConstraintViolationException::class, 'renditions_media_asset_id_label_unique');
        expect(fn () => attemptMedia(fn () => DB::table('renditions')->insert($row(['label' => '1080p', 'width' => 0]))))->toThrow(QueryException::class, 'renditions_size_range');
        expect(fn () => attemptMedia(fn () => DB::table('renditions')->insert($row(['label' => '480p', 'video_bitrate_kbps' => 0]))))->toThrow(QueryException::class, 'renditions_bitrate_range');
    });

    it('deletes the media and its renditions with the movie', function () {
        $asset = F::inStatus(MediaStatus::Ready);
        $movie = Catalog::movie(tmdbId: 4242);
        app(TitleRepository::class)->save($movie);
        $stored = MediaAsset::reconstitute($asset->id(), PlayableRef::title(PlayableId::fromString($movie->id()->value)), $asset->sourcePath(), MediaStatus::Ready, $asset->duration(), null, $asset->hlsMasterPath(), $asset->renditions());
        $this->repository->save($stored);

        DB::table('titles')->where('id', $movie->id()->value)->delete();

        expect(DB::table('media_assets')->count())->toBe(0)->and(DB::table('renditions')->count())->toBe(0);
    });
});
