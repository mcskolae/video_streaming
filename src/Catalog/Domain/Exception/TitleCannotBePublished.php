<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Exception;

use DomainException;
use HomeFlix\Catalog\Domain\Model\TitleId;

final class TitleCannotBePublished extends DomainException
{
    public static function notPlayable(TitleId $id): self
    {
        return new self(sprintf('Title %s cannot be published: no media is ready to play.', $id));
    }
}
