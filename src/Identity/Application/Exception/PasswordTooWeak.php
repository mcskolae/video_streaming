<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Exception;

use InvalidArgumentException;

final class PasswordTooWeak extends InvalidArgumentException
{
    public static function tooShort(int $minimum): self
    {
        return new self(sprintf('An administrator password must be at least %d characters long.', $minimum));
    }
}
