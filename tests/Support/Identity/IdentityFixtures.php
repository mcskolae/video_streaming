<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use HomeFlix\Identity\Domain\Model\Email;
use HomeFlix\Identity\Domain\Model\Role;
use HomeFlix\Identity\Domain\Model\User;
use HomeFlix\Identity\Domain\Model\UserId;
use Ramsey\Uuid\Uuid;

final class IdentityFixtures
{
    public static function userId(): UserId
    {
        return UserId::fromString(Uuid::uuid7()->toString());
    }

    public static function user(string $email = 'alice@example.org', Role $role = Role::User): User
    {
        return User::reconstitute(self::userId(), 'Alice', Email::fromString($email), 'hash', $role);
    }
}
