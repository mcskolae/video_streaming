<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Query;

use HomeFlix\Catalog\Domain\Model\TitleStatus;

/** Une série vue de l'administration : saisons, épisodes et état de chaque épisode. */
final readonly class AdminSeries
{
    /** @param  list<AdminSeason>  $seasons */
    public function __construct(
        public string $id,
        public string $name,
        public TitleStatus $status,
        public array $seasons,
    ) {}

    public function hasPlayableEpisode(): bool
    {
        foreach ($this->seasons as $season) {
            foreach ($season->episodes as $episode) {
                if ($episode->playable) {
                    return true;
                }
            }
        }

        return false;
    }
}
