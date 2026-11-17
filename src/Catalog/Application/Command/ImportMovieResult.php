<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Command;

use HomeFlix\Catalog\Domain\Model\TitleId;

final readonly class ImportMovieResult
{
    public function __construct(
        public TitleId $titleId,
        /** Vrai si le film vient d'être ajouté, faux si sa fiche existait déjà (elle a été mise à jour). */
        public bool $created,
    ) {}
}
