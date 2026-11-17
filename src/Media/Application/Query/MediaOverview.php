<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Query;

use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableKind;

/** Ce que l'écran d'envoi affiche : la cible, et l'état de sa vidéo s'il y en a une. */
final readonly class MediaOverview
{
    /** @param  list<string>  $renditionLabels */
    public function __construct(
        public PlayableKind $kind,
        public string $targetId,
        public string $label,
        public ?MediaStatus $status,
        public ?string $errorMessage,
        public ?int $durationSeconds,
        public array $renditionLabels,
    ) {}

    /** Un envoi est possible s'il n'y a pas de vidéo, ou si la précédente a échoué. */
    public function canUpload(): bool
    {
        return $this->status === null || $this->status === MediaStatus::Failed;
    }
}
