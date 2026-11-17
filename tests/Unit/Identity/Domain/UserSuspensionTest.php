<?php

declare(strict_types=1);

use HomeFlix\Identity\Domain\Exception\CannotRemoveLastAdministrator;
use HomeFlix\Identity\Domain\Model\Role;
use Tests\Support\Identity\IdentityFixtures as F;

$at = new DateTimeImmutable('2026-11-17 12:00:00');

describe('last administrator rule', function () use ($at) {
    it('lets an administrator lose the role when another active administrator remains', function () {
        $admin = F::user('a@example.org', Role::Admin);

        expect($admin->changeRole(Role::User, 2))->toBeTrue()->and($admin->role())->toBe(Role::User);
    });

    it('refuses to demote the last active administrator', function () {
        F::user('a@example.org', Role::Admin)->changeRole(Role::User, 1);
    })->throws(CannotRemoveLastAdministrator::class);

    it('refuses to suspend the last active administrator', function () use ($at) {
        F::user('a@example.org', Role::Admin)->suspend($at, 1);
    })->throws(CannotRemoveLastAdministrator::class);

    it('suspends an administrator when another active one remains', function () use ($at) {
        $admin = F::user('a@example.org', Role::Admin);

        expect($admin->suspend($at, 2))->toBeTrue()->and($admin->isSuspended())->toBeTrue();
    });

    it('does not count a suspended administrator as active', function () use ($at) {
        $suspended = F::user('s@example.org', Role::Admin);
        $suspended->suspend($at, 2);

        expect($suspended->isActiveAdministrator())->toBeFalse();
        // Le dernier administrateur actif : le suspendu ne compte pas dans le total reçu.
        expect(fn () => F::user('b@example.org', Role::Admin)->changeRole(Role::User, 1))->toThrow(CannotRemoveLastAdministrator::class);
    });

    it('lets a suspended administrator lose the role, it was not active anyway', function () use ($at) {
        $suspended = F::user('s@example.org', Role::Admin);
        $suspended->suspend($at, 2);

        expect($suspended->changeRole(Role::User, 1))->toBeTrue();
    });

    it('never blocks a simple user', function () use ($at) {
        $user = F::user('u@example.org');

        expect($user->suspend($at, 0))->toBeTrue()
            ->and($user->changeRole(Role::Admin, 0))->toBeTrue();
    });

    it('allows promoting whatever the count is', function () {
        expect(F::user('u@example.org')->changeRole(Role::Admin, 0))->toBeTrue();
    });
});

describe('suspension', function () use ($at) {
    it('keeps the original date when suspended twice', function () use ($at) {
        $user = F::user();
        $user->suspend($at, 1);

        expect($user->suspend($at->modify('+1 day'), 1))->toBeFalse()->and($user->suspendedAt())->toEqual($at);
    });

    it('reactivates a suspended account and ignores an active one', function () use ($at) {
        $user = F::user();

        expect($user->reactivate())->toBeFalse();

        $user->suspend($at, 1);

        expect($user->reactivate())->toBeTrue()->and($user->isSuspended())->toBeFalse()->and($user->suspendedAt())->toBeNull();
    });

    it('does nothing when the role does not change', function () {
        expect(F::user()->changeRole(Role::User, 1))->toBeFalse();
    });
});
