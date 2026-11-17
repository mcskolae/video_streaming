<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Exception;

use DomainException;

final class EpisodeNotFound extends DomainException
{
    public static function inSeason(int $season, int $episode): self
    {
        return new self(sprintf('Episode %d does not exist in season %d.', $episode, $season));
    }
}
