<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Exception;

use DomainException;

final class RenditionAlreadyExists extends DomainException
{
    public static function withLabel(string $label): self
    {
        return new self(sprintf('A rendition "%s" already exists for this media.', $label));
    }
}
