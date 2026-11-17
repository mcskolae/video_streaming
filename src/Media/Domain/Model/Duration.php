<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Model;

use InvalidArgumentException;

/** Durée d'une vidéo, en secondes entières, de 1 seconde à 24 heures. */
final readonly class Duration
{
    public const MAX_SECONDS = 86400;

    public function __construct(public int $seconds)
    {
        if ($seconds < 1 || $seconds > self::MAX_SECONDS) {
            throw new InvalidArgumentException(sprintf('A duration is 1 to %d seconds, got %d.', self::MAX_SECONDS, $seconds));
        }
    }
}
