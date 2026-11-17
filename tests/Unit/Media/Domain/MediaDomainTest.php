<?php

declare(strict_types=1);

use HomeFlix\Media\Domain\Event\MediaAssetFailed;
use HomeFlix\Media\Domain\Event\MediaAssetReady;
use HomeFlix\Media\Domain\Exception\InvalidMediaTransition;
use HomeFlix\Media\Domain\Exception\RenditionAlreadyExists;
use HomeFlix\Media\Domain\Model\Duration;
use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableKind;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Domain\Model\RelativePath;
use HomeFlix\Media\Domain\Model\Rendition;
use Tests\Support\MediaFixtures as F;

describe('Duration', function () {
    it('accepts 1 second to 24 hours', function (int $seconds) {
        expect((new Duration($seconds))->seconds)->toBe($seconds);
    })->with([[1], [600], [86400]]);

    it('refuses anything else', function (int $seconds) {
        new Duration($seconds);
    })->with([[0], [-1], [86401]])->throws(InvalidArgumentException::class);
});

describe('RelativePath', function () {
    it('accepts a safe relative path', function (string $path) {
        expect((new RelativePath($path))->value)->toBe($path);
    })->with([['source.mp4'], ['incoming/abc-123/source.mp4'], ['hls/0190a6e4/720p/index.m3u8'], ['a_b/c.d-e']]);

    it('refuses a path that leaves the storage root or looks like something else', function (string $path) {
        new RelativePath($path);
    })->with([
        'parent' => ['../etc/passwd'],
        'parent inside' => ['hls/../../etc/passwd'],
        'double dot in a name' => ['hls/a..b/index.m3u8'],
        'absolute' => ['/etc/passwd'],
        'current dir' => ['./source.mp4'],
        'current dir inside' => ['hls/./index.m3u8'],
        'empty' => [''],
        'backslash' => ['hls\\index.m3u8'],
        'null byte' => ["hls/index.m3u8\0.jpg"],
        'space' => ['my video.mp4'],
        'url' => ['https://evil.example/x.m3u8'],
        'scheme' => ['file:///etc/passwd'],
        'trailing slash' => ['hls/'],
        'double slash' => ['hls//index.m3u8'],
        'leading dot' => ['.hidden'],
        'newline' => ["a\nb"],
        'too long' => [str_repeat('a', 256)],
        'accent' => ['vidéo.mp4'],
        'percent encoding' => ['%2e%2e/x'],
    ])->throws(InvalidArgumentException::class);
});

describe('Rendition', function () {
    it('accepts a normal quality', function () {
        $rendition = F::rendition('1080p', 1920, 1080);

        expect($rendition->label)->toBe('1080p')->and($rendition->width)->toBe(1920);
    });

    it('refuses an invalid quality', function (string $label, int $width, int $height, int $bitrate) {
        new Rendition($label, $width, $height, $bitrate, new RelativePath('hls/x/index.m3u8'));
    })->with([
        'bad label' => ['HD', 1280, 720, 2500],
        'label with path' => ['../720p', 1280, 720, 2500],
        'zero width' => ['720p', 0, 720, 2500],
        'huge height' => ['720p', 1280, 8193, 2500],
        'zero bitrate' => ['720p', 1280, 720, 0],
        'huge bitrate' => ['720p', 1280, 720, 100001],
    ])->throws(InvalidArgumentException::class);
});

describe('PlayableRef', function () {
    it('points to a movie or to an episode', function () {
        $id = F::playableId();

        expect(PlayableRef::title($id)->kind)->toBe(PlayableKind::Title)
            ->and(PlayableRef::episode($id)->kind)->toBe(PlayableKind::Episode);
    });

    it('is equal only for the same kind and the same id', function () {
        $id = F::playableId();

        expect(PlayableRef::title($id)->equals(PlayableRef::title($id)))->toBeTrue()
            ->and(PlayableRef::title($id)->equals(PlayableRef::episode($id)))->toBeFalse()
            ->and(PlayableRef::title($id)->equals(PlayableRef::title(F::playableId())))->toBeFalse();
    });
});

describe('MediaAsset transitions', function () {
    it('starts as uploaded, without duration, error, stream or event', function () {
        $asset = F::uploaded();

        expect($asset->status())->toBe(MediaStatus::Uploaded)
            ->and($asset->duration())->toBeNull()
            ->and($asset->errorMessage())->toBeNull()
            ->and($asset->hlsMasterPath())->toBeNull()
            ->and($asset->renditions())->toBe([])
            ->and($asset->isReady())->toBeFalse()
            ->and($asset->pullDomainEvents())->toBe([]);
    });

    it('goes through the whole normal path', function () {
        $asset = F::uploaded();

        $asset->queue();
        expect($asset->status())->toBe(MediaStatus::Queued);

        $asset->startProcessing();
        expect($asset->status())->toBe(MediaStatus::Processing);

        $asset->markReady(new Duration(600), new RelativePath('hls/abc/master.m3u8'), [F::rendition('720p'), F::rendition('1080p', 1920, 1080)], F::now());
        expect($asset->status())->toBe(MediaStatus::Ready)
            ->and($asset->isReady())->toBeTrue()
            ->and($asset->duration()?->seconds)->toBe(600)
            ->and($asset->hlsMasterPath()?->value)->toBe('hls/abc/master.m3u8')
            ->and(array_map(fn ($r) => $r->label, $asset->renditions()))->toBe(['720p', '1080p']);
    });

    /*
     * Matrice complète : chaque action est essayée depuis chaque état.
     * Les seules combinaisons permises sont celles du docblock de MediaAsset.
     */
    $allowed = [
        'queue' => [MediaStatus::Uploaded],
        'startProcessing' => [MediaStatus::Queued],
        'markReady' => [MediaStatus::Processing],
        'markFailed' => [MediaStatus::Uploaded, MediaStatus::Queued, MediaStatus::Processing],
        'retry' => [MediaStatus::Failed],
    ];

    $actions = [
        'queue' => fn (MediaAsset $a) => $a->queue(),
        'startProcessing' => fn (MediaAsset $a) => $a->startProcessing(),
        'markReady' => fn (MediaAsset $a) => $a->markReady(new Duration(60), new RelativePath('hls/x/master.m3u8'), [F::rendition()], F::now()),
        'markFailed' => fn (MediaAsset $a) => $a->markFailed('erreur', F::now()),
        'retry' => fn (MediaAsset $a) => $a->retry(),
    ];

    foreach ($allowed as $action => $okStatuses) {
        foreach (MediaStatus::cases() as $status) {
            $isAllowed = in_array($status, $okStatuses, true);

            it(($isAllowed ? 'allows ' : 'forbids ')."{$action} from {$status->value}", function () use ($actions, $action, $status, $isAllowed) {
                $asset = F::inStatus($status);

                if ($isAllowed) {
                    $actions[$action]($asset);
                    expect($asset->status())->not->toBe($status);

                    return;
                }

                expect(fn () => $actions[$action]($asset))->toThrow(InvalidMediaTransition::class);
                expect($asset->status())->toBe($status)->and($asset->pullDomainEvents())->toBe([]);
            });
        }
    }

    it('never lets a ready media go back to processing', function () {
        $asset = F::inStatus(MediaStatus::Ready);

        expect(fn () => $asset->startProcessing())->toThrow(InvalidMediaTransition::class, 'start processing');
        expect(fn () => $asset->queue())->toThrow(InvalidMediaTransition::class);
        expect(fn () => $asset->retry())->toThrow(InvalidMediaTransition::class);
        expect(fn () => $asset->markFailed('x', F::now()))->toThrow(InvalidMediaTransition::class);
        expect($asset->status())->toBe(MediaStatus::Ready);
    });

    it('says in the error which action was refused from which state', function () {
        expect(fn () => F::inStatus(MediaStatus::Ready)->startProcessing())
            ->toThrow(InvalidMediaTransition::class, 'Cannot start processing a media that is "ready"');
    });
});

describe('ready', function () {
    it('announces that the media is ready, with its target', function () {
        $target = PlayableRef::episode(F::playableId());
        $asset = F::uploaded($target);
        $asset->queue();
        $asset->startProcessing();

        $asset->markReady(new Duration(60), new RelativePath('hls/x/master.m3u8'), [F::rendition()], F::now());

        $events = $asset->pullDomainEvents();
        $event = $events[0] ?? null;
        expect($events)->toHaveCount(1)->and($event)->toBeInstanceOf(MediaAssetReady::class);
        assert($event instanceof MediaAssetReady);
        expect($event->assetId->equals($asset->id()))->toBeTrue()
            ->and($event->target->equals($target))->toBeTrue()
            ->and($event->occurredOn())->toEqual(F::now());
    });

    it('needs at least one rendition and stays in processing otherwise', function () {
        $asset = F::inStatus(MediaStatus::Processing);

        try {
            $asset->markReady(new Duration(60), new RelativePath('hls/x/master.m3u8'), [], F::now());
            $this->fail('An exception was expected.');
        } catch (InvalidArgumentException) {
            expect($asset->status())->toBe(MediaStatus::Processing)->and($asset->pullDomainEvents())->toBe([]);
        }
    });

    it('refuses two renditions with the same label and stays in processing', function () {
        $asset = F::inStatus(MediaStatus::Processing);

        try {
            $asset->markReady(new Duration(60), new RelativePath('hls/x/master.m3u8'), [F::rendition('720p'), F::rendition('720p')], F::now());
            $this->fail('An exception was expected.');
        } catch (RenditionAlreadyExists) {
            expect($asset->status())->toBe(MediaStatus::Processing)->and($asset->renditions())->toBe([]);
        }
    });
});

describe('failure and retry', function () {
    it('keeps the message and announces the failure', function () {
        $asset = F::inStatus(MediaStatus::Processing);

        $asset->markFailed("  ffmpeg: codec non supporté \n", F::now());

        $events = $asset->pullDomainEvents();
        expect($asset->status())->toBe(MediaStatus::Failed)
            ->and($asset->errorMessage())->toBe('ffmpeg: codec non supporté')
            ->and($events)->toHaveCount(1)
            ->and($events[0])->toBeInstanceOf(MediaAssetFailed::class);
    });

    it('needs a message', function (string $message) {
        $asset = F::inStatus(MediaStatus::Queued);

        try {
            $asset->markFailed($message, F::now());
            $this->fail('An exception was expected.');
        } catch (InvalidArgumentException) {
            expect($asset->status())->toBe(MediaStatus::Queued);
        }
    })->with([[''], ['   '], ["\n\t"]]);

    it('cuts a very long message', function () {
        $asset = F::inStatus(MediaStatus::Queued);

        $asset->markFailed(str_repeat('é', 5000), F::now());

        expect(mb_strlen((string) $asset->errorMessage()))->toBe(2000);
    });

    it('puts a failed media back in the queue without its error', function () {
        $asset = F::inStatus(MediaStatus::Failed);

        $asset->retry();

        expect($asset->status())->toBe(MediaStatus::Queued)->and($asset->errorMessage())->toBeNull();
    });

    it('can fail, retry and fail again', function () {
        $asset = F::inStatus(MediaStatus::Uploaded);

        $asset->markFailed('un', F::now());
        $asset->retry();
        $asset->startProcessing();
        $asset->markFailed('deux', F::now());

        expect($asset->status())->toBe(MediaStatus::Failed)->and($asset->errorMessage())->toBe('deux');
    });
});

it('knows the media it belongs to', function () {
    $target = PlayableRef::title(F::playableId());

    expect(F::uploaded($target)->target()->equals($target))->toBeTrue();
});
