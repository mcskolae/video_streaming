<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Model;

enum TitleType: string
{
    case Movie = 'movie';
    case Series = 'series';
}
