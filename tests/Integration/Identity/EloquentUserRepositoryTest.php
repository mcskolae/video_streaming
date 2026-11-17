<?php

declare(strict_types=1);

use App\Models\User as UserRecord;
use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\Role;
use HomeFlix\Identity\Domain\Model\User;
use HomeFlix\Identity\Domain\Repository\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CatalogFixtures;
use Tests\Support\Identity\IdentityFixtures as F;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(UserRepository::class);
});

it('saves a new administrator and reloads it', function () {
    $admin = User::registerAdministrator(F::userId(), 'Admin', Email::fromString('Admin@Example.org'), Hash::make('a-long-enough-password'));

    $this->repository->save($admin);
    $loaded = $this->repository->findByEmail(Email::fromString('admin@example.org'));

    expect($loaded)->not->toBeNull()
        ->and($loaded->id()->equals($admin->id()))->toBeTrue()
        ->and($loaded->name())->toBe('Admin')
        ->and($loaded->role())->toBe(Role::Admin)
        ->and(Hash::check('a-long-enough-password', $loaded->passwordHash()))->toBeTrue()
        ->and(DB::table('users')->where('email', 'admin@example.org')->value('role'))->toBe('admin');
});

it('returns null for an unknown address', function () {
    expect($this->repository->findByEmail(Email::fromString('nobody@example.org')))->toBeNull();
});

it('persists a promotion on the same row', function () {
    $user = F::user('alice@example.org');
    $this->repository->save($user);

    $loaded = $this->repository->findByEmail($user->email());
    $loaded->promoteToAdministrator();
    $this->repository->save($loaded);

    expect(DB::table('users')->count())->toBe(1)
        ->and($this->repository->findByEmail($user->email())->role())->toBe(Role::Admin);
});

it('finds an account created through the Laravel model, whatever the case of the address', function () {
    UserRecord::factory()->create(['email' => 'bob@example.org']);

    $found = $this->repository->findByEmail(Email::fromString('BOB@example.org'));

    expect($found)->not->toBeNull()->and($found->role())->toBe(Role::User);
});

it('stores a version 7 UUID as the identifier of a user created through Laravel', function () {
    $user = UserRecord::factory()->create();

    expect(CatalogFixtures::uuidVersion($user->id))->toBe(7);
});
