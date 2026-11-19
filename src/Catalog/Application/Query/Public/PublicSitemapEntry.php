<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query\Public;

use DateTimeImmutable;

/** Un titre publié, tel qu'il figure dans le plan du site (sitemap.xml). */
final readonly class PublicSitemapEntry
{
    public function __construct(
        public string $slug,
        /** Date de la dernière modification de la fiche : les moteurs de recherche s'en servent pour revenir la lire. */
        public DateTimeImmutable $lastModified,
    ) {}
}
