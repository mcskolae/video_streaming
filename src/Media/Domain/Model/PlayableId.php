<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Model;

use HomeFlix\Shared\Domain\Uuid;

/**
 * Identifiant de ce que le média illustre : un film ou un épisode du contexte Catalog.
 * Le contexte Media n'importe pas les types de Catalog : il ne garde que l'UUID.
 */
final readonly class PlayableId extends Uuid {}
