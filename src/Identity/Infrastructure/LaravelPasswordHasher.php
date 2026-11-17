<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Infrastructure;

use HomeFlix\Identity\Application\Port\PasswordHasher;
use Illuminate\Contracts\Hashing\Hasher;

final readonly class LaravelPasswordHasher implements PasswordHasher
{
    public function __construct(private Hasher $hasher) {}

    public function hash(#[\SensitiveParameter] string $plainPassword): string
    {
        return $this->hasher->make($plainPassword);
    }
}
