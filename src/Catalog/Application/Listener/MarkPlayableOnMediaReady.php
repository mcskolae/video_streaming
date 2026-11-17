<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Application\Listener;

use HomeFlix\Catalog\Domain\Model\EpisodeId;
use HomeFlix\Catalog\Domain\Model\TitleId;
use HomeFlix\Catalog\Domain\Model\TitleType;
use HomeFlix\Catalog\Domain\Repository\TitleRepository;
use HomeFlix\Media\Domain\Event\MediaAssetReady;

/**
 * Quand le flux d'un film ou d'un épisode est prêt, le catalogue le marque lisible : le titre peut alors être publié.
 *
 * Le contexte Catalog ne connaît du contexte Media que cet événement. Le traitement est idempotent :
 * recevoir deux fois le même événement ne change rien.
 */
final readonly class MarkPlayableOnMediaReady
{
    public function __construct(private TitleRepository $titles) {}

    public function handle(MediaAssetReady $event): void
    {
        if ($event->targetKind() === 'title') {
            $title = $this->titles->find(TitleId::fromString($event->targetId()));

            // Le titre a pu être supprimé entre l'envoi et la fin du transcodage : il n'y a plus rien à marquer.
            if ($title === null || $title->type() !== TitleType::Movie) {
                return;
            }

            $title->markPlayable();
        } else {
            $title = $this->titles->findByEpisodeId(EpisodeId::fromString($event->targetId()));

            if ($title === null) {
                return;
            }

            $title->markEpisodePlayableById(EpisodeId::fromString($event->targetId()));
        }

        $this->titles->save($title);
    }
}
