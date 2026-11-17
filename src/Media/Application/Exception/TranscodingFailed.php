<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Exception;

use RuntimeException;

/** La vidéo n'a pas pu être analysée ou convertie. Le message est affiché à l'administrateur. */
final class TranscodingFailed extends RuntimeException
{
    public static function notAVideo(): self
    {
        return new self('Le fichier n\'est pas une vidéo lisible.');
    }

    public static function unreadable(string $detail): self
    {
        return new self('Le fichier n\'a pas pu être analysé : '.$detail);
    }

    public static function ffmpeg(string $detail): self
    {
        return new self('La conversion a échoué : '.$detail);
    }

    public static function noOutput(): self
    {
        return new self('La conversion n\'a produit aucun flux lisible.');
    }
}
