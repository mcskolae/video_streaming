<?php

declare(strict_types=1);

namespace HomeFlix\Library\Domain\Model;

use HomeFlix\Shared\Domain\Uuid;

/** Identifiant d'une vidéo (un film ou un épisode). Le contexte Library n'importe pas les types de Media. */
final readonly class MediaRef extends Uuid {}
