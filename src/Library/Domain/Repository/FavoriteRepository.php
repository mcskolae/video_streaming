<?php

declare(strict_types=1);

namespace HomeFlix\Library\Domain\Repository;

use HomeFlix\Library\Domain\Model\Favorite;
use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Model\TitleRef;

interface FavoriteRepository
{
    /** Ajoute un favori. Sans effet s'il existe déjà : la date d'origine est conservée. */
    public function add(Favorite $favorite): void;

    /** Retire un favori. Sans effet s'il n'existe pas. */
    public function remove(MemberId $member, TitleRef $title): void;

    public function exists(MemberId $member, TitleRef $title): bool;
}
