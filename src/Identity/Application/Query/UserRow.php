<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Query;

use DateTimeImmutable;
use HomeFlix\Identity\Domain\Model\Role;

/** Une ligne de la liste d'administration : données de lecture, pas l'agrégat. */
final readonly class UserRow
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public Role $role,
        public ?DateTimeImmutable $suspendedAt,
        public DateTimeImmutable $registeredAt,
    ) {}

    public function isSuspended(): bool
    {
        return $this->suspendedAt !== null;
    }
}
