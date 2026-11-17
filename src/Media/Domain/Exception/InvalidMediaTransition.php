<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Exception;

use DomainException;
use HomeFlix\Media\Domain\Model\MediaStatus;

final class InvalidMediaTransition extends DomainException
{
    public static function from(MediaStatus $status, string $action): self
    {
        return new self(sprintf('Cannot %s a media that is "%s".', $action, $status->value));
    }
}
