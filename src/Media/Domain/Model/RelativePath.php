<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Model;

use InvalidArgumentException;
use Stringable;

/**
 * Chemin relatif à la racine de stockage des médias.
 *
 * Ces chemins servent à lire et à servir des fichiers : ils ne peuvent donc ni sortir de la racine
 * (`..`, chemin absolu), ni contenir de caractère inattendu (octet nul, antislash, espace, URL).
 */
final readonly class RelativePath implements Stringable
{
    private const PATTERN = '#^[A-Za-z0-9][A-Za-z0-9._-]*(?:/[A-Za-z0-9][A-Za-z0-9._-]*)*$#';

    public const MAX_LENGTH = 255;

    public string $value;

    public function __construct(string $value)
    {
        if (strlen($value) > self::MAX_LENGTH || preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a safe relative path.', addcslashes($value, "\0..\37")));
        }

        foreach (explode('/', $value) as $segment) {
            if ($segment === '.' || $segment === '..' || str_contains($segment, '..')) {
                throw new InvalidArgumentException(sprintf('"%s" is not a safe relative path.', $value));
            }
        }

        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
