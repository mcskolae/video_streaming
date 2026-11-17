<?php

declare(strict_types=1);

namespace HomeFlix\Library\Domain\Model;

use DateTimeImmutable;

/** Un titre mis de côté par un membre. Unique par membre et par titre. */
final readonly class Favorite
{
    public function __construct(
        public MemberId $member,
        public TitleRef $title,
        public DateTimeImmutable $markedAt,
    ) {}
}
