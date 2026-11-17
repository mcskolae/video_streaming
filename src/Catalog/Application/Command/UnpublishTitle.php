<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Command;

final readonly class UnpublishTitle
{
    public function __construct(public string $titleId) {}
}
