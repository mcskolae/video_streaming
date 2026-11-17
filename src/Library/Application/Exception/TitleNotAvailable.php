<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Exception;

use RuntimeException;

/** Le titre n'existe pas ou n'est pas publié : on ne peut pas le mettre en favori. */
final class TitleNotAvailable extends RuntimeException
{
    public static function create(): self
    {
        return new self('Ce titre n\'est pas disponible.');
    }
}
