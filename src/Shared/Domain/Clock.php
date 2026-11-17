<?php

declare(strict_types=1);

namespace HomeFlix\Shared\Domain;

use DateTimeImmutable;

/** Port : le domaine ne lit jamais l'heure directement, pour rester testable. */
interface Clock
{
    public function now(): DateTimeImmutable;
}
