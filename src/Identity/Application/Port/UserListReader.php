<?php

declare(strict_types=1);

namespace HomeFlix\Identity\Application\Port;

use HomeFlix\Identity\Application\Query\UserPage;

/** Port de lecture : la liste se lit directement, sans reconstruire les agrégats. */
interface UserListReader
{
    /** Tous les comptes, les plus récents d'abord. Les pages commencent à 1. */
    public function page(int $page, int $perPage): UserPage;
}
