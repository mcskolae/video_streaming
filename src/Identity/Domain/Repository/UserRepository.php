<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Domain\Repository;

use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\User;

interface UserRepository
{
    public function findByEmail(Email $email): ?User;

    /** Crée ou met à jour le compte. */
    public function save(User $user): void;
}
