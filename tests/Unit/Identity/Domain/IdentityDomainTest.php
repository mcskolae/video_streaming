<?php

declare(strict_types=1);

use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\Role;
use HomeFlix\Identity\Domain\Model\User;
use Tests\Support\Identity\IdentityFixtures as F;

describe('Role', function () {
    it('only the admin role is an administrator', function () {
        expect(Role::Admin->isAdministrator())->toBeTrue()
            ->and(Role::User->isAdministrator())->toBeFalse();
    });

    it('is stored under stable values', function () {
        expect(Role::from('user'))->toBe(Role::User)
            ->and(Role::from('admin'))->toBe(Role::Admin);
    });
});

describe('Email', function () {
    it('normalizes to lowercase and trims', function () {
        expect(Email::fromString('  Alice@Example.ORG ')->value)->toBe('alice@example.org');
    });

    it('treats addresses that differ only by case as equal', function () {
        expect(Email::fromString('A@x.org')->equals(Email::fromString('a@X.ORG')))->toBeTrue();
    });

    it('rejects an invalid address', function (string $invalid) {
        Email::fromString($invalid);
    })->with(['', '   ', 'not-an-email', 'a@', '@x.org', 'a b@x.org', 'a@@x.org'])
        ->throws(InvalidArgumentException::class);

    it('rejects an address longer than 255 characters', function () {
        Email::fromString(str_repeat('a', 250).'@x.org');
    })->throws(InvalidArgumentException::class);
});

describe('User', function () {
    it('registers an administrator', function () {
        $user = User::registerAdministrator(F::userId(), 'Admin', Email::fromString('admin@x.org'), 'hash');

        expect($user->isAdministrator())->toBeTrue()
            ->and($user->role())->toBe(Role::Admin)
            ->and($user->name())->toBe('Admin');
    });

    it('promotes a simple user and reports the change', function () {
        $user = F::user();

        expect($user->promoteToAdministrator())->toBeTrue()
            ->and($user->isAdministrator())->toBeTrue();
    });

    it('does not report a change when promoting an administrator', function () {
        $user = F::user(role: Role::Admin);

        expect($user->promoteToAdministrator())->toBeFalse()
            ->and($user->isAdministrator())->toBeTrue();
    });

    it('trims the name', function () {
        $user = User::reconstitute(F::userId(), '  Bob  ', Email::fromString('b@x.org'), 'hash', Role::User);

        expect($user->name())->toBe('Bob');
    });

    it('rejects invalid data', function (string $name, string $hash) {
        User::reconstitute(F::userId(), $name, Email::fromString('b@x.org'), $hash, Role::User);
    })->with([
        'empty name' => ['  ', 'hash'],
        'name too long' => [str_repeat('a', 256), 'hash'],
        'no password hash' => ['Bob', ''],
    ])->throws(InvalidArgumentException::class);
});
