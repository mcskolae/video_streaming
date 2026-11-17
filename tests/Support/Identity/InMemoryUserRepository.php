<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\User;
use HomeFlix\Identity\Domain\Repository\UserRepository;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    public int $saves = 0;

    public function findByEmail(Email $email): ?User
    {
        return $this->users[$email->value] ?? null;
    }

    public function save(User $user): void
    {
        $this->users[$user->email()->value] = $user;
        $this->saves++;
    }

    public function count(): int
    {
        return count($this->users);
    }
}
