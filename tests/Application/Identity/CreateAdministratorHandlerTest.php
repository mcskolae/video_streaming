<?php

declare(strict_types=1);

use HomeFlix\Identity\Application\Command\CreateAdministrator;
use HomeFlix\Identity\Application\Command\CreateAdministratorHandler;
use HomeFlix\Identity\Application\Command\CreateAdministratorResult;
use HomeFlix\Identity\Application\Exception\PasswordRequired;
use HomeFlix\Identity\Application\Exception\PasswordTooWeak;
use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\Role;
use HomeFlix\Shared\Application\IdGenerator;
use Ramsey\Uuid\Uuid;
use Tests\Support\Identity\FakePasswordHasher;
use Tests\Support\Identity\IdentityFixtures as F;
use Tests\Support\Identity\InMemoryUserRepository;

beforeEach(function () {
    $this->users = new InMemoryUserRepository;
    $this->handler = new CreateAdministratorHandler(
        $this->users,
        new FakePasswordHasher,
        new class implements IdGenerator
        {
            public function generate(): string
            {
                return Uuid::uuid7()->toString();
            }
        },
    );
});

it('creates an administrator with a hashed password', function () {
    $result = $this->handler->handle(new CreateAdministrator('Admin@Example.org', 'Admin', 'a-long-enough-password'));

    $user = $this->users->findByEmail(Email::fromString('admin@example.org'));
    expect($result)->toBe(CreateAdministratorResult::Created)
        ->and($user)->not->toBeNull()
        ->and($user->isAdministrator())->toBeTrue()
        ->and($user->name())->toBe('Admin')
        ->and($user->passwordHash())->not->toContain('a-long-enough-password')
        ->and($user->passwordHash())->toBe('hashed:'.strrev('a-long-enough-password'));
});

it('promotes an existing user and keeps their password', function () {
    $this->users->save(F::user('alice@example.org'));
    $before = $this->users->findByEmail(Email::fromString('alice@example.org'))->passwordHash();

    $result = $this->handler->handle(new CreateAdministrator('ALICE@example.org'));

    $user = $this->users->findByEmail(Email::fromString('alice@example.org'));
    expect($result)->toBe(CreateAdministratorResult::Promoted)
        ->and($user->role())->toBe(Role::Admin)
        ->and($user->passwordHash())->toBe($before)
        ->and($this->users->count())->toBe(1);
});

it('does nothing for an account that is already an administrator', function () {
    $this->users->save(F::user('root@example.org', Role::Admin));
    $saves = $this->users->saves;

    $result = $this->handler->handle(new CreateAdministrator('root@example.org'));

    expect($result)->toBe(CreateAdministratorResult::AlreadyAdministrator)
        ->and($this->users->saves)->toBe($saves);
});

it('asks for a name and a password when the account does not exist', function (?string $name, ?string $password) {
    $this->handler->handle(new CreateAdministrator('new@example.org', $name, $password));
})->with([
    'nothing' => [null, null],
    'no password' => ['Admin', null],
    'no name' => [null, 'a-long-enough-password'],
    'blank name' => ['  ', 'a-long-enough-password'],
])->throws(PasswordRequired::class);

it('creates nothing when the account is refused', function () {
    try {
        $this->handler->handle(new CreateAdministrator('new@example.org'));
    } catch (PasswordRequired) {
    }

    expect($this->users->count())->toBe(0);
});

it('refuses a password shorter than 12 characters', function () {
    $this->handler->handle(new CreateAdministrator('new@example.org', 'Admin', 'short-pass1'));
})->throws(PasswordTooWeak::class);

it('accepts a password of exactly 12 characters', function () {
    $result = $this->handler->handle(new CreateAdministrator('new@example.org', 'Admin', '123456789012'));

    expect($result)->toBe(CreateAdministratorResult::Created);
});

it('rejects an invalid email address', function () {
    $this->handler->handle(new CreateAdministrator('not-an-email', 'Admin', 'a-long-enough-password'));
})->throws(InvalidArgumentException::class);
