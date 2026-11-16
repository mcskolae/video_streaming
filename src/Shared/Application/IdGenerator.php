<?php

declare(strict_types=1);

namespace HomeFlix\Shared\Application;

interface IdGenerator
{
    /** Retourne un nouvel UUID sous forme de chaîne. */
    public function generate(): string;
}
