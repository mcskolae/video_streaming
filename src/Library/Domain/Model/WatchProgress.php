<?php

declare(strict_types=1);

namespace HomeFlix\Library\Domain\Model;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Où un membre en est dans une vidéo. Unique par membre et par vidéo.
 *
 * Invariants : 0 <= position <= durée, et la lecture est terminée au-delà de 90 % de la durée.
 */
final readonly class WatchProgress
{
    /** Au-delà de cette part de la durée, la vidéo compte comme vue. */
    public const COMPLETED_RATIO = 0.9;

    public bool $completed;

    public function __construct(
        public MemberId $member,
        public MediaRef $media,
        public int $positionSeconds,
        public int $durationSeconds,
        public DateTimeImmutable $updatedAt,
    ) {
        if ($durationSeconds < 1) {
            throw new InvalidArgumentException('A duration is at least one second.');
        }

        if ($positionSeconds < 0 || $positionSeconds > $durationSeconds) {
            throw new InvalidArgumentException('A position is between zero and the duration.');
        }

        $this->completed = $positionSeconds >= $durationSeconds * self::COMPLETED_RATIO;
    }

    /** Une position reçue du lecteur est ramenée dans la durée : un lecteur peut dépasser de quelques centièmes. */
    public static function record(MemberId $member, MediaRef $media, float $positionSeconds, int $durationSeconds, DateTimeImmutable $at): self
    {
        if (! is_finite($positionSeconds)) {
            throw new InvalidArgumentException('A position is a finite number.');
        }

        return new self($member, $media, (int) max(0, min($durationSeconds, floor($positionSeconds))), $durationSeconds, $at);
    }
}
