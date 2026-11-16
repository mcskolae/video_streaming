<?php

declare(strict_types=1);

use HomeFlix\Shared\Domain\Uuid;

final readonly class FirstTestId extends Uuid {}
final readonly class SecondTestId extends Uuid {}

const VALID_UUID = '0190a6e4-7c3b-7c1e-9a52-3f1d5c8e2b10';

it('accepts a valid uuid and normalizes it to lowercase', function () {
    $id = FirstTestId::fromString(strtoupper(VALID_UUID));

    expect($id->value)->toBe(VALID_UUID)
        ->and((string) $id)->toBe(VALID_UUID);
});

it('rejects an invalid uuid', function (string $invalid) {
    FirstTestId::fromString($invalid);
})->with(['', 'not-a-uuid', '0190a6e4-7c3b-7c1e-9a52-3f1d5c8e2b1', '0190a6e4-7c3b-0c1e-9a52-3f1d5c8e2b10'])
    ->throws(InvalidArgumentException::class);

it('is equal to another id of the same type and value', function () {
    expect(FirstTestId::fromString(VALID_UUID)->equals(FirstTestId::fromString(VALID_UUID)))->toBeTrue();
});

it('is never equal to an id of another type, even with the same value', function () {
    expect(FirstTestId::fromString(VALID_UUID)->equals(SecondTestId::fromString(VALID_UUID)))->toBeFalse();
});
