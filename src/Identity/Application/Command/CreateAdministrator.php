<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Command;

/** Crée un administrateur, ou promeut le compte existant portant cette adresse. */
final readonly class CreateAdministrator
{
    public function __construct(
        public string $email,
        public ?string $name = null,
        #[\SensitiveParameter] public ?string $plainPassword = null,
    ) {}
}
