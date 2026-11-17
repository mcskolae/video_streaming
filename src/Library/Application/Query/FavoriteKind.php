<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Query;

enum FavoriteKind: string
{
    case Movie = 'movie';
    case Series = 'series';
}
