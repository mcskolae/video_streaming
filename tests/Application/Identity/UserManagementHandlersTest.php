<?php

declare(strict_types=1);

use HomeFlix\Identity\Application\Command\ChangeUserRole;
use HomeFlix\Identity\Application\Command\ChangeUserRoleHandler;
use HomeFlix\Identity\Application\Command\ReactivateUser;
use HomeFlix\Identity\Application\Command\ReactivateUserHandler;
use HomeFlix\Identity\Application\Command\SuspendUser;
use HomeFlix\Identity\Application\Command\SuspendUserHandler;
use HomeFlix\Identity\Application\Exception\UserNotFound;
use HomeFlix\Identity\Domain\Exception\CannotRemoveLastAdministrator;
use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\Role;
use Ramsey\Uuid\Uuid;
use Tests\Support\FixedClock;
use Tests\Support\Identity\IdentityFixtures as F;
use Tests\Support\Identity\InMemoryUserRepository;

beforeEach(function () {
    $this->users = new InMemoryUserRepository;
    $this->admin = F::user('admin@example.org', Role::Admin);
    $this->other = F::user('other@example.org', Role::Admin);
    $this->member = F::user('member@example.org');
    foreach ([$this->admin, $this->other, $this->member] as $user) {
        $this->users->save($user);
    }
    $this->role = new ChangeUserRoleHandler($this->users);
    $this->suspend = new SuspendUserHandler($this->users, new FixedClock);
    $this->reactivate = new ReactivateUserHandler($this->users);
});

it('promotes a member', function () {
    $this->role->handle(new ChangeUserRole($this->member->id()->value, 'admin'));

    expect($this->users->findByEmail(Email::fromString('member@example.org'))->role())->toBe(Role::Admin);
});

it('demotes an administrator while another one remains', function () {
    $this->role->handle(new ChangeUserRole($this->admin->id()->value, 'user'));

    expect($this->admin->role())->toBe(Role::User);
});

it('refuses to demote the last administrator, whoever asks, and saves nothing', function () {
    $this->role->handle(new ChangeUserRole($this->other->id()->value, 'user'));
    $saves = $this->users->saves;

    expect(fn () => $this->role->handle(new ChangeUserRole($this->admin->id()->value, 'user')))->toThrow(CannotRemoveLastAdministrator::class);
    expect($this->admin->role())->toBe(Role::Admin)->and($this->users->saves)->toBe($saves);
});

it('refuses to suspend the last administrator', function () {
    $this->suspend->handle(new SuspendUser($this->other->id()->value));

    expect(fn () => $this->suspend->handle(new SuspendUser($this->admin->id()->value)))->toThrow(CannotRemoveLastAdministrator::class);
    expect($this->admin->isSuspended())->toBeFalse();
});

it('does not let two suspended administrators leave nobody in charge', function () {
    $this->suspend->handle(new SuspendUser($this->admin->id()->value));

    expect(fn () => $this->role->handle(new ChangeUserRole($this->other->id()->value, 'user')))->toThrow(CannotRemoveLastAdministrator::class);
});

it('suspends with the clock of the application, then reactivates', function () {
    $this->suspend->handle(new SuspendUser($this->member->id()->value));
    expect($this->member->suspendedAt())->toEqual(new DateTimeImmutable('2026-11-17 12:00:00'));

    $this->reactivate->handle(new ReactivateUser($this->member->id()->value));
    expect($this->member->isSuspended())->toBeFalse();
});

it('reactivating an administrator makes them count as active again', function () {
    $this->suspend->handle(new SuspendUser($this->admin->id()->value));
    $this->reactivate->handle(new ReactivateUser($this->admin->id()->value));

    $this->role->handle(new ChangeUserRole($this->other->id()->value, 'user'));
    expect($this->other->role())->toBe(Role::User);
});

it('reports an unknown account', function () {
    $id = Uuid::uuid7()->toString();

    expect(fn () => $this->suspend->handle(new SuspendUser($id)))->toThrow(UserNotFound::class);
    expect(fn () => $this->reactivate->handle(new ReactivateUser($id)))->toThrow(UserNotFound::class);
    expect(fn () => $this->role->handle(new ChangeUserRole($id, 'admin')))->toThrow(UserNotFound::class);
});

it('refuses an unknown role and an invalid identifier', function () {
    expect(fn () => $this->role->handle(new ChangeUserRole($this->member->id()->value, 'root')))->toThrow(InvalidArgumentException::class);
    expect(fn () => $this->suspend->handle(new SuspendUser('pas-un-uuid')))->toThrow(InvalidArgumentException::class);
});
