<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Command;

final readonly class AddFavorite
{
    public function __construct(public string $memberId, public string $titleId) {}
}
