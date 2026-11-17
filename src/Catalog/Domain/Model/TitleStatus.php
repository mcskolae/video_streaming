<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Domain\Model;

enum TitleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
