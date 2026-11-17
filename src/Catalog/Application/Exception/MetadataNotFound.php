<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Exception;

use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Model\TmdbId;
use RuntimeException;

/** Le fournisseur de métadonnées ne connaît pas ce titre. */
final class MetadataNotFound extends RuntimeException
{
    public static function forTitle(TitleType $type, TmdbId $id): self
    {
        return new self(sprintf('No %s found for TMDB id %d.', $type->value, $id->value));
    }
}
