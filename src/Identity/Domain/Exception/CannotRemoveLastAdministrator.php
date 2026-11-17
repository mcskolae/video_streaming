<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Domain\Exception;

use DomainException;

final class CannotRemoveLastAdministrator extends DomainException
{
    public static function create(): self
    {
        return new self('The last active administrator cannot lose the role or be suspended.');
    }
}
