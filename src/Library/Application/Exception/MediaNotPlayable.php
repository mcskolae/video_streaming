<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Exception;

use RuntimeException;

final class MediaNotPlayable extends RuntimeException
{
    public static function create(): self
    {
        return new self('This video does not exist or is not playable.');
    }
}
