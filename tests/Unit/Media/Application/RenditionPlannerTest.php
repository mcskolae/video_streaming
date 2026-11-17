<?php

declare(strict_types=1);

use HomeFlix\Media\Application\Transcoding\RenditionPlan;
use HomeFlix\Media\Application\Transcoding\RenditionPlanner;

/** @return list<string> */
function labels(int $width, int $height): array
{
    return array_map(fn (RenditionPlan $plan) => $plan->label, RenditionPlanner::plan($width, $height));
}

it('produces 1080p and 720p from a full HD source or bigger', function (int $width, int $height) {
    expect(labels($width, $height))->toBe(['1080p', '720p']);
})->with([[1920, 1080], [3840, 2160], [2560, 1440], [1920, 1200]]);

it('produces only 720p from an HD source', function (int $width, int $height) {
    expect(labels($width, $height))->toBe(['720p']);
})->with([[1280, 720], [1600, 900], [1920, 800]]);

it('never goes above the source', function (int $width, int $height) {
    foreach (RenditionPlanner::plan($width, $height) as $plan) {
        expect($plan->height)->toBeLessThanOrEqual($height);
    }
})->with([[640, 360], [1280, 720], [1920, 1080], [320, 240], [1000, 719]]);

it('gives a small source a single rendition at its own height', function (int $width, int $height, string $label) {
    $plans = RenditionPlanner::plan($width, $height);

    expect($plans)->toHaveCount(1)
        ->and($plans[0]->label)->toBe($label)
        ->and($plans[0]->height)->toBe($height - ($height % 2))
        ->and($plans[0]->videoBitrateKbps)->toBe(1200);
})->with([[640, 360, '360p'], [854, 480, '480p'], [1280, 719, '718p'], [426, 240, '240p']]);

it('keeps the aspect ratio with even dimensions', function (int $width, int $height) {
    foreach (RenditionPlanner::plan($width, $height) as $plan) {
        expect($plan->width % 2)->toBe(0)
            ->and($plan->height % 2)->toBe(0)
            ->and(abs($plan->width / $plan->height - $width / $height))->toBeLessThan(0.02);
    }
})->with([[1920, 1080], [1920, 800], [1440, 1080], [1280, 720], [1081, 1081], [1000, 719]]);

it('computes the exact sizes of the usual case', function () {
    $plans = RenditionPlanner::plan(1920, 1080);

    expect([$plans[0]->width, $plans[0]->height, $plans[0]->videoBitrateKbps])->toBe([1920, 1080, 5000])
        ->and([$plans[1]->width, $plans[1]->height, $plans[1]->videoBitrateKbps])->toBe([1280, 720, 2500]);
});

it('gives labels the domain accepts', function (int $width, int $height) {
    foreach (RenditionPlanner::plan($width, $height) as $plan) {
        expect($plan->label)->toMatch('/^[0-9]{3,4}p$/');
    }
})->with([[1920, 1080], [256, 144], [200, 150], [2, 2000]]);

it('refuses unusable sizes', function (int $width, int $height) {
    RenditionPlanner::plan($width, $height);
})->with([[0, 0], [1, 1080], [1920, 1], [-5, 720], [20000, 1080], [1920, 20000]])->throws(InvalidArgumentException::class);
