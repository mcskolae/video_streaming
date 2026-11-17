<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Command;

final readonly class PublishTitle
{
    public function __construct(public string $titleId) {}
}
