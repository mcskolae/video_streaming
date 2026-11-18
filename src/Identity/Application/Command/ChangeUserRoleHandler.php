<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Command;

use HomeFlix\Identity\Application\Exception\UserNotFound;
use HomeFlix\Identity\Domain\Exception\CannotRemoveLastAdministrator;
use HomeFlix\Identity\Domain\Model\Role;
use HomeFlix\Identity\Domain\Model\User;
use HomeFlix\Identity\Domain\Model\UserId;
use HomeFlix\Identity\Domain\Repository\UserRepository;
use InvalidArgumentException;

final readonly class ChangeUserRoleHandler
{
    public function __construct(private UserRepository $users) {}

    /**
     * @throws InvalidArgumentException si l'identifiant n'est pas un UUID ou si le rôle est inconnu
     * @throws UserNotFound
     * @throws CannotRemoveLastAdministrator
     */
    public function handle(ChangeUserRole $command): void
    {
        $id = UserId::fromString($command->userId);
        $role = Role::tryFrom($command->role) ?? throw new InvalidArgumentException('Unknown role.');

        $this->users->change($id, fn (User $user, int $administrators) => $user->changeRole($role, $administrators));
    }
}
