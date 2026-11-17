<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Domain\Model;

/** Rôle d'un compte. Le visiteur sans compte n'a pas de rôle. */
enum Role: string
{
    case User = 'user';
    case Admin = 'admin';

    public function isAdministrator(): bool
    {
        return $this === self::Admin;
    }
}
