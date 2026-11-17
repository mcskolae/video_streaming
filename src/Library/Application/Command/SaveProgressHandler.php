<?php

declare(strict_types=1);

namespace HomeFlix\Library\Application\Command;

use HomeFlix\Library\Application\Exception\MediaNotPlayable;
use HomeFlix\Library\Application\Port\MediaDirectory;
use HomeFlix\Library\Domain\Model\MediaRef;
use HomeFlix\Library\Domain\Model\MemberId;
use HomeFlix\Library\Domain\Model\WatchProgress;
use HomeFlix\Library\Domain\Repository\WatchProgressRepository;
use HomeFlix\Shared\Domain\Clock;
use InvalidArgumentException;

final readonly class SaveProgressHandler
{
    public function __construct(
        private WatchProgressRepository $progress,
        private MediaDirectory $media,
        private Clock $clock,
    ) {}

    /**
     * La durée vient du serveur, jamais du lecteur : la position ne peut pas la dépasser.
     *
     * @throws InvalidArgumentException si un identifiant n'est pas un UUID ou si la position n'est pas un nombre fini
     * @throws MediaNotPlayable si la vidéo n'existe pas ou n'est pas lisible
     */
    public function handle(SaveProgress $command): void
    {
        $member = MemberId::fromString($command->memberId);
        $media = MediaRef::fromString($command->mediaId);

        $duration = $this->media->playableDuration($media) ?? throw MediaNotPlayable::create();

        $this->progress->save(WatchProgress::record($member, $media, $command->positionSeconds, $duration, $this->clock->now()));
    }
}
