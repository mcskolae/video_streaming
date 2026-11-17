<?php

declare(strict_types=1);

use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Media\Application\Port\MediaListReader;
use HomeFlix\Media\Domain\Model\Duration;
use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableId;
use HomeFlix\Media\Domain\Model\PlayableKind;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Domain\Model\RelativePath;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogFixtures as Catalog;
use Tests\Support\MediaFixtures as F;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->reader = app(MediaListReader::class);
    $this->assets = app(MediaAssetRepository::class);
});

/** Un média pour un nouveau film, dans l'état demandé, avec une date de mise à jour fixée. */
function mediaFor(string $name, MediaStatus $status, string $updatedAt): MediaAsset
{
    static $n = 100;
    $n++;
    $movie = Catalog::movie(tmdbId: $n, name: $name);
    app(TitleRepository::class)->save($movie);

    $asset = MediaAsset::reconstitute(
        F::assetId(), PlayableRef::title(PlayableId::fromString($movie->id()->value)), new RelativePath('incoming/x/source.mp4'), $status,
        $status === MediaStatus::Ready ? new Duration(754) : null,
        $status === MediaStatus::Failed ? "Erreur de {$name}" : null,
        $status === MediaStatus::Ready ? new RelativePath('hls/x/master.m3u8') : null,
        $status === MediaStatus::Ready ? [F::rendition('1080p', 1920, 1080), F::rendition('720p')] : [],
    );
    app(MediaAssetRepository::class)->save($asset);
    DB::table('media_assets')->where('id', $asset->id()->value)->update(['updated_at' => $updatedAt]);

    return $asset;
}

it('lists the most recently updated videos first', function () {
    mediaFor('Ancien', MediaStatus::Ready, '2026-01-01 10:00:00');
    mediaFor('Récent', MediaStatus::Queued, '2026-03-01 10:00:00');
    mediaFor('Milieu', MediaStatus::Failed, '2026-02-01 10:00:00');

    expect(array_map(fn ($row) => $row->label, $this->reader->recent(10)))->toBe(['Récent', 'Milieu', 'Ancien']);
});

it('maps the state, the duration, the error and the qualities', function () {
    mediaFor('Prêt', MediaStatus::Ready, '2026-03-01 10:00:00');
    mediaFor('En échec', MediaStatus::Failed, '2026-02-01 10:00:00');

    $rows = [];
    foreach ($this->reader->recent(10) as $row) {
        $rows[$row->label] = $row;
    }

    expect($rows['Prêt']->status)->toBe(MediaStatus::Ready)
        ->and($rows['Prêt']->durationSeconds)->toBe(754)
        ->and($rows['Prêt']->renditionLabels)->toBe(['720p', '1080p'])
        ->and($rows['Prêt']->kind)->toBe(PlayableKind::Title)
        ->and($rows['Prêt']->canRetry())->toBeFalse()
        ->and($rows['Prêt']->isActive())->toBeFalse()
        ->and($rows['En échec']->errorMessage)->toBe('Erreur de En échec')
        ->and($rows['En échec']->canRetry())->toBeTrue()
        ->and($rows['En échec']->durationSeconds)->toBeNull();
});

it('names the series, the season and the episode for an episode', function () {
    $series = Catalog::series(tmdbId: 1, name: 'Sintel Chronicles');
    app(TitleRepository::class)->save($series);
    $episode = $series->season(1)->episode(2);
    $this->assets->save(MediaAsset::upload(F::assetId(), PlayableRef::episode(PlayableId::fromString($episode->id->value)), new RelativePath('incoming/x/source.mp4')));

    $row = $this->reader->recent(10)[0];

    expect($row->label)->toBe('Sintel Chronicles, S1E2')->and($row->kind)->toBe(PlayableKind::Episode)->and($row->targetId)->toBe($episode->id->value);
});

it('says which videos are still being handled', function (MediaStatus $status, bool $active) {
    mediaFor('Film', $status, '2026-03-01 10:00:00');

    expect($this->reader->recent(10)[0]->isActive())->toBe($active);
})->with([
    [MediaStatus::Uploaded, true], [MediaStatus::Queued, true], [MediaStatus::Processing, true], [MediaStatus::Ready, false], [MediaStatus::Failed, false],
]);

it('respects the limit and returns nothing for an empty list', function () {
    expect($this->reader->recent(10))->toBe([]);

    foreach (range(1, 5) as $i) {
        mediaFor("Film {$i}", MediaStatus::Queued, sprintf('2026-03-%02d 10:00:00', $i));
    }

    expect($this->reader->recent(3))->toHaveCount(3)->and($this->reader->recent(3)[0]->label)->toBe('Film 5');
});

it('uses the same number of queries whatever the number of videos', function () {
    $measure = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->reader->recent(100);

        return count(DB::getQueryLog());
    };

    mediaFor('Un', MediaStatus::Ready, '2026-03-01 10:00:00');
    $few = $measure();
    foreach (range(1, 30) as $i) {
        mediaFor("Film {$i}", $i % 2 ? MediaStatus::Ready : MediaStatus::Failed, sprintf('2026-02-%02d 10:00:00', ($i % 28) + 1));
    }

    expect($measure())->toBe($few)->and($few)->toBeLessThanOrEqual(2);
});
