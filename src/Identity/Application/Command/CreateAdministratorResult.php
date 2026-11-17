<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Command;

enum CreateAdministratorResult
{
    case Created;
    case Promoted;
    case AlreadyAdministrator;
}
