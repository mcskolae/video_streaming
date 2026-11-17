<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Model;

enum MediaStatus: string
{
    /** Fichier reçu et validé, pas encore en file. */
    case Uploaded = 'uploaded';

    /** En attente d'un transcodage. */
    case Queued = 'queued';

    /** Transcodage en cours. */
    case Processing = 'processing';

    /** Flux HLS prêt à être lu. */
    case Ready = 'ready';

    /** Échec, avec un message. Peut être relancé. */
    case Failed = 'failed';
}
