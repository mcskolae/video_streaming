<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Exception;

use RuntimeException;

/** Le compte n'existe pas encore : il faut un nom et un mot de passe pour le créer. */
final class PasswordRequired extends RuntimeException
{
    public static function forNewAccount(string $email): self
    {
        return new self(sprintf('No account exists for %s: a name and a password are required to create it.', $email));
    }
}
