<?php

declare(strict_types=1);

namespace HomeFlix\Library\Infrastructure\Persistence;

use HomeFlix\Library\Application\Port\TitleDirectory;
use HomeFlix\Library\Domain\Model\TitleRef;
use Illuminate\Support\Facades\DB;

final class DatabaseTitleDirectory implements TitleDirectory
{
    public function isPublished(TitleRef $title): bool
    {
        return DB::table('titles')->where('id', $title->value)->where('status', 'published')->exists();
    }
}
