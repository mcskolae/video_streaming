<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Command;

use HomeFlix\Identity\Application\Exception\PasswordRequired;
use HomeFlix\Identity\Application\Exception\PasswordTooWeak;
use HomeFlix\Identity\Application\Port\PasswordHasher;
use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\User;
use HomeFlix\Identity\Domain\Model\UserId;
use HomeFlix\Identity\Domain\Repository\UserRepository;
use HomeFlix\Shared\Application\IdGenerator;

final readonly class CreateAdministratorHandler
{
    public const MINIMUM_PASSWORD_LENGTH = 12;

    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private IdGenerator $ids,
    ) {}

    /**
     * Un compte existant est promu sans toucher à son mot de passe. Sinon, un nom et un mot de passe
     * sont nécessaires pour créer le compte.
     *
     * @throws PasswordRequired quand le compte n'existe pas et que le nom ou le mot de passe manque
     * @throws PasswordTooWeak quand le mot de passe est trop court
     */
    public function handle(CreateAdministrator $command): CreateAdministratorResult
    {
        $email = Email::fromString($command->email);
        $existing = $this->users->findByEmail($email);

        if ($existing !== null) {
            if (! $existing->promoteToAdministrator()) {
                return CreateAdministratorResult::AlreadyAdministrator;
            }

            $this->users->save($existing);

            return CreateAdministratorResult::Promoted;
        }

        if ($command->name === null || trim($command->name) === '' || $command->plainPassword === null) {
            throw PasswordRequired::forNewAccount($email->value);
        }

        if (mb_strlen($command->plainPassword) < self::MINIMUM_PASSWORD_LENGTH) {
            throw PasswordTooWeak::tooShort(self::MINIMUM_PASSWORD_LENGTH);
        }

        $this->users->save(User::registerAdministrator(
            UserId::fromString($this->ids->generate()),
            $command->name,
            $email,
            $this->hasher->hash($command->plainPassword),
        ));

        return CreateAdministratorResult::Created;
    }
}
