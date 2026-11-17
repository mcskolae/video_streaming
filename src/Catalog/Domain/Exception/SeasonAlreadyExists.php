<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Exception;

use DomainException;

final class SeasonAlreadyExists extends DomainException
{
    public static function withNumber(int $number): self
    {
        return new self(sprintf('Season %d already exists.', $number));
    }
}
