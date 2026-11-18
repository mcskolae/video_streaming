<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Exception;

use RuntimeException;

final class UserNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('No user with id "%s".', $id));
    }
}
