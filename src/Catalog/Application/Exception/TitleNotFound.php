<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Exception;

use HomeFlix\Catalog\Domain\Model\TitleId;
use RuntimeException;

final class TitleNotFound extends RuntimeException
{
    public static function withId(TitleId $id): self
    {
        return new self(sprintf('Title %s does not exist.', $id));
    }
}
