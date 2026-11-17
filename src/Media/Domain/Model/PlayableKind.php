<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Model;

enum PlayableKind: string
{
    /** Un film : le média est rattaché au titre. */
    case Title = 'title';

    /** Un épisode de série. */
    case Episode = 'episode';
}
