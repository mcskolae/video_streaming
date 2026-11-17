<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Query;

use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableKind;

/** Une ligne de la liste des vidéos, pour l'administration. */
final readonly class AdminMediaRow
{
    /** @param  list<string>  $renditionLabels */
    public function __construct(
        public string $id,
        public PlayableKind $kind,
        public string $targetId,
        public string $label,
        public MediaStatus $status,
        public ?int $durationSeconds,
        public ?string $errorMessage,
        public array $renditionLabels,
        public \DateTimeImmutable $updatedAt,
    ) {}

    /** La conversion n'est pas finie : la page doit se rafraîchir. */
    public function isActive(): bool
    {
        return in_array($this->status, [MediaStatus::Uploaded, MediaStatus::Queued, MediaStatus::Processing], true);
    }

    public function canRetry(): bool
    {
        return $this->status === MediaStatus::Failed;
    }
}
