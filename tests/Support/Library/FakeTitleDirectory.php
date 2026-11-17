<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use HomeFlix\Library\Application\Port\TitleDirectory;
use HomeFlix\Library\Domain\Model\TitleRef;

final class FakeTitleDirectory implements TitleDirectory
{
    /** @var list<string> */
    public array $published = [];

    public function isPublished(TitleRef $title): bool
    {
        return in_array($title->value, $this->published, true);
    }
}
