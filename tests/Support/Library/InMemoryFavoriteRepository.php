<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use HomeFlix\Library\Domain\Model\Favorite;
use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Model\TitleRef;
use HomeFlix\Library\Domain\Repository\FavoriteRepository;

final class InMemoryFavoriteRepository implements FavoriteRepository
{
    /** @var array<string, Favorite> */
    public array $stored = [];

    public int $writes = 0;

    public function add(Favorite $favorite): void
    {
        $key = $favorite->member->value.'|'.$favorite->title->value;

        // Comme la base : un favori existant n'est pas remplacé.
        if (! isset($this->stored[$key])) {
            $this->stored[$key] = $favorite;
            $this->writes++;
        }
    }

    public function remove(MemberId $member, TitleRef $title): void
    {
        unset($this->stored[$member->value.'|'.$title->value]);
    }

    public function exists(MemberId $member, TitleRef $title): bool
    {
        return isset($this->stored[$member->value.'|'.$title->value]);
    }
}
