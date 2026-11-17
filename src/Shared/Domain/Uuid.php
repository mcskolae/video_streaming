<?php

declare(strict_types=1);

namespace HomeFlix\Shared\Domain;

use InvalidArgumentException;
use Stringable;

/**
 * Identifiant UUID. Chaque agrégat définit son propre type (TitleId, UserId, ...)
 * en étendant cette classe, pour qu'on ne puisse pas confondre deux identifiants.
 */
abstract readonly class Uuid implements Stringable
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    final protected function __construct(public string $value) {}

    final public static function fromString(string $value): static
    {
        $normalized = strtolower($value);

        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid UUID.', $value));
        }

        return new static($normalized);
    }

    final public function equals(self $other): bool
    {
        return $other::class === static::class && $other->value === $this->value;
    }

    final public function __toString(): string
    {
        return $this->value;
    }
}
