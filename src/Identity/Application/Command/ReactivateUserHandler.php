<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Command;

use HomeFlix\Identity\Application\Exception\UserNotFound;
use HomeFlix\Identity\Domain\Model\User;
use HomeFlix\Identity\Domain\Model\UserId;
use HomeFlix\Identity\Domain\Repository\UserRepository;
use InvalidArgumentException;

final readonly class ReactivateUserHandler
{
    public function __construct(private UserRepository $users) {}

    /**
     * @throws InvalidArgumentException si l'identifiant n'est pas un UUID
     * @throws UserNotFound
     */
    public function handle(ReactivateUser $command): void
    {
        $this->users->change(
            UserId::fromString($command->userId),
            fn (User $user) => $user->reactivate(),
        );
    }
}
