<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Exception;

use RuntimeException;

/** L'envoi est refusé. Le message est affiché à l'administrateur : il ne contient ni chemin ni détail technique. */
final class InvalidUpload extends RuntimeException
{
    public static function unsupportedFormat(): self
    {
        return new self('Format non accepté. Formats acceptés : mp4, m4v, mov, mkv, webm, avi.');
    }

    public static function empty(): self
    {
        return new self('Le fichier est vide.');
    }

    public static function tooLarge(int $maxMegabytes): self
    {
        return new self(sprintf('Le fichier dépasse la taille maximale (%d Mo).', $maxMegabytes));
    }

    public static function inconsistentChunks(): self
    {
        return new self('Le découpage annoncé ne correspond pas à la taille du fichier.');
    }

    public static function unknownTarget(): self
    {
        return new self('Ce film ou cet épisode n\'existe pas.');
    }

    public static function mediaAlreadyExists(): self
    {
        return new self('Une vidéo existe déjà pour ce titre. Seule une vidéo en échec peut être remplacée.');
    }

    public static function unknownUpload(): self
    {
        return new self('Envoi inconnu ou expiré. Recommencez l\'envoi.');
    }

    public static function badChunk(): self
    {
        return new self('Morceau invalide : numéro ou taille incorrects.');
    }

    public static function incomplete(): self
    {
        return new self('Tous les morceaux ne sont pas arrivés.');
    }

    public static function sizeMismatch(): self
    {
        return new self('La taille reçue ne correspond pas à la taille annoncée.');
    }

    public static function notAVideo(): self
    {
        return new self('Le fichier n\'est pas une vidéo valide.');
    }
}
