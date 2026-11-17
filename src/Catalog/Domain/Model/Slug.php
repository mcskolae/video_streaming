<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Model;

use InvalidArgumentException;
use Stringable;
use Transliterator;

/** Fragment d'URL lisible : lettres minuscules, chiffres et tirets. */
final readonly class Slug implements Stringable
{
    private const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const MAX_LENGTH = 120;

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        if (strlen($value) > self::MAX_LENGTH || preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid slug.', $value));
        }

        return new self($value);
    }

    /** Construit un slug depuis un titre : "Amélie Poulain", 2001 donne "amelie-poulain-2001". */
    public static function fromTitle(string $title, ?int $year = null): self
    {
        $transliterator = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        $ascii = $transliterator?->transliterate($title);

        if ($ascii === false || $ascii === null) {
            throw new InvalidArgumentException('Unable to transliterate the title.');
        }

        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');

        if ($base === '') {
            throw new InvalidArgumentException(sprintf('Cannot build a slug from "%s".', $title));
        }

        $suffix = $year === null ? '' : '-'.$year;
        $base = rtrim(substr($base, 0, self::MAX_LENGTH - strlen($suffix)), '-');

        return self::fromString($base.$suffix);
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
