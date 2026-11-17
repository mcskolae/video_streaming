<?php

declare(strict_types=1);

namespace HomeFlix\Library\Domain\Model;

use HomeFlix\Shared\Domain\Uuid;

/** Identifiant d'un membre (un compte connecté). Le contexte Library n'importe pas les types d'Identity. */
final readonly class MemberId extends Uuid {}
