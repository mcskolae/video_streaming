<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Exception;

use DomainException;

final class UnsupportedForTitleType extends DomainException
{
    public static function seasonsOnMovie(): self
    {
        return new self('A movie has no seasons or episodes.');
    }

    public static function playableFlagOnSeries(): self
    {
        return new self('A series is playable through its episodes, not as a whole.');
    }
}
