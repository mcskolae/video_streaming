<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Exception;

use DomainException;

final class EpisodeAlreadyExists extends DomainException
{
    public static function inSeason(int $season, int $episode): self
    {
        return new self(sprintf('Episode %d already exists in season %d.', $episode, $season));
    }
}
