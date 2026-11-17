<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Exception;

use RuntimeException;

/** Le fichier source n'existe plus : la vidéo ne peut pas être relancée, il faut la renvoyer. */
final class SourceMissing extends RuntimeException
{
    public static function create(): self
    {
        return new self('Le fichier source n\'existe plus : renvoyez la vidéo.');
    }
}
