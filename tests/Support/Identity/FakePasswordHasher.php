<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use HomeFlix\Identity\Application\Port\PasswordHasher;

final class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return 'hashed:'.strrev($plainPassword);
    }
}
