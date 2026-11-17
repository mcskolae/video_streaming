<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Closure;
use HomeFlix\Identity\Application\Exception\UserNotFound;
use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\User;
use HomeFlix\Identity\Domain\Model\UserId;
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

    public function findById(UserId $id): ?User
    {
        foreach ($this->users as $user) {
            if ($user->id()->equals($id)) {
                return $user;
            }
        }

        return null;
    }

    public function save(User $user): void
    {
        $this->users[$user->email()->value] = $user;
        $this->saves++;
    }

    public function change(UserId $id, Closure $change): void
    {
        $user = $this->findById($id) ?? throw UserNotFound::withId($id->value);
        $administrators = count(array_filter($this->users, fn (User $u) => $u->isActiveAdministrator()));

        $change($user, $administrators);

        $this->save($user);
    }

    public function count(): int
    {
        return count($this->users);
    }
}
