<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Exception;

use RuntimeException;

final class MediaNotFound extends RuntimeException
{
    public static function create(): self
    {
        return new self('Cette vidéo n\'existe pas.');
    }
}
