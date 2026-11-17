<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Command;

use HomeFlix\Identity\Application\Exception\UserNotFound;
use HomeFlix\Identity\Domain\Exception\CannotRemoveLastAdministrator;
use HomeFlix\Identity\Domain\Model\User;
use HomeFlix\Identity\Domain\Model\UserId;
use HomeFlix\Identity\Domain\Repository\UserRepository;
use HomeFlix\Shared\Domain\Clock;
use InvalidArgumentException;

final readonly class SuspendUserHandler
{
    public function __construct(
        private UserRepository $users,
        private Clock $clock,
    ) {}

    /**
     * La session d'un compte suspendu est fermée à sa prochaine requête (middleware de la couche UI).
     *
     * @throws InvalidArgumentException si l'identifiant n'est pas un UUID
     * @throws UserNotFound
     * @throws CannotRemoveLastAdministrator
     */
    public function handle(SuspendUser $command): void
    {
        $at = $this->clock->now();

        $this->users->change(
            UserId::fromString($command->userId),
            fn (User $user, int $administrators) => $user->suspend($at, $administrators),
        );
    }
}
