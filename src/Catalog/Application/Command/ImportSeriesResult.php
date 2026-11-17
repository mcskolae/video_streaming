<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Command;

use HomeFlix\Catalog\Domain\Model\TitleId;

final readonly class ImportSeriesResult
{
    public function __construct(
        public TitleId $titleId,
        /** Vrai si la série vient d'être ajoutée, faux si elle existait déjà (elle a été mise à jour). */
        public bool $created,
    ) {}
}
