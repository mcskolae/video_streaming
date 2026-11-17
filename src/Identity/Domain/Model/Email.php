<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Domain\Model;

use InvalidArgumentException;
use Stringable;

/** Adresse e-mail, toujours normalisée en minuscules. */
final readonly class Email implements Stringable
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $normalized = mb_strtolower(trim($value));

        if ($normalized === '' || strlen($normalized) > 255 || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid email address.', $value));
        }

        return new self($normalized);
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
