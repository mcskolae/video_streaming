<?php

declare(strict_types=1);

use HomeFlix\Library\Domain\Model\MediaRef;
use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Model\WatchProgress;
use Ramsey\Uuid\Uuid;

function watchProgressFor(float $position, int $duration = 1000): WatchProgress
{
    return WatchProgress::record(
        MemberId::fromString(Uuid::uuid7()->toString()),
        MediaRef::fromString(Uuid::uuid7()->toString()),
        $position,
        $duration,
        new DateTimeImmutable('2026-11-17 12:00:00'),
    );
}

it('is not completed below 90 % of the duration', function () {
    expect(watchProgressFor(0)->completed)->toBeFalse()
        ->and(watchProgressFor(500)->completed)->toBeFalse()
        ->and(watchProgressFor(899)->completed)->toBeFalse();
});

it('is completed from 90 % of the duration', function () {
    expect(watchProgressFor(900)->completed)->toBeTrue()
        ->and(watchProgressFor(950)->completed)->toBeTrue()
        ->and(watchProgressFor(1000)->completed)->toBeTrue();
});

it('brings a position beyond the duration back to the duration', function () {
    $progress = watchProgressFor(1000.4);

    expect($progress->positionSeconds)->toBe(1000)->and($progress->completed)->toBeTrue();
    expect(watchProgressFor(5000)->positionSeconds)->toBe(1000);
});

it('brings a negative position back to zero and drops the decimals', function () {
    expect(watchProgressFor(-3)->positionSeconds)->toBe(0)
        ->and(watchProgressFor(12.9)->positionSeconds)->toBe(12);
});

it('refuses a position that is not a number', function (float $position) {
    watchProgressFor($position);
})->with([NAN, INF, -INF])->throws(InvalidArgumentException::class);

it('refuses a duration below one second', function () {
    watchProgressFor(0, 0);
})->throws(InvalidArgumentException::class);

it('refuses to be built with a position beyond the duration', function () {
    new WatchProgress(
        MemberId::fromString(Uuid::uuid7()->toString()),
        MediaRef::fromString(Uuid::uuid7()->toString()),
        11,
        10,
        new DateTimeImmutable,
    );
})->throws(InvalidArgumentException::class);
