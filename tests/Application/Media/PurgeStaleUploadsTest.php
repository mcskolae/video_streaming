<?php

declare(strict_types=1);

use HomeFlix\Media\Application\Command\PurgeStaleUploadsHandler;
use Tests\Support\FixedClock;
use Tests\Support\Media\FakeUploadStore;

it('purges the uploads that have been inactive for more than 24 hours', function () {
    $store = new FakeUploadStore;
    $store->purgeResult = 3;

    $purged = (new PurgeStaleUploadsHandler($store, new FixedClock(new DateTimeImmutable('2026-11-17 12:00:00'))))->handle();

    expect($purged)->toBe(3)
        ->and($store->purgedBefore?->format('Y-m-d H:i:s'))->toBe('2026-11-16 12:00:00');
});
