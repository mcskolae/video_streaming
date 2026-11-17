<?php

declare(strict_types=1);

namespace HomeFlix\Media\Domain\Model;

/** Ce qu'un média illustre : un film (identifiant du titre) ou un épisode (identifiant de l'épisode). */
final readonly class PlayableRef
{
    private function __construct(public PlayableKind $kind, public PlayableId $id) {}

    public static function title(PlayableId $titleId): self
    {
        return new self(PlayableKind::Title, $titleId);
    }

    public static function episode(PlayableId $episodeId): self
    {
        return new self(PlayableKind::Episode, $episodeId);
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind && $this->id->equals($other->id);
    }
}
