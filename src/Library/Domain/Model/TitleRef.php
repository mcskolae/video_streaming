<?php

declare(strict_types=1);

namespace HomeFlix\Library\Domain\Model;

use HomeFlix\Shared\Domain\Uuid;

/** Identifiant d'un titre du catalogue. Le contexte Library n'importe pas les types de Catalog. */
final readonly class TitleRef extends Uuid {}
