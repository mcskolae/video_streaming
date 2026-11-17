<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Infrastructure;

use HomeFlix\Identity\Application\Port\PasswordHasher;
use HomeFlix\Identity\Application\Port\UserListReader;
use HomeFlix\Identity\Domain\Repository\UserRepository;
use HomeFlix\Identity\Infrastructure\Persistence\DatabaseUserListReader;
use HomeFlix\Identity\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

/** Lie les ports du domaine et de l'application à leurs adaptateurs. */
final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(UserListReader::class, DatabaseUserListReader::class);
    }
}
