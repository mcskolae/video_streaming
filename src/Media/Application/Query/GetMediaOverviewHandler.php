<?php

declare(strict_types=1);

namespace HomeFlix\Media\Application\Query;

use HomeFlix\Media\Application\Port\PlayableCatalog;
use HomeFlix\Media\Domain\Model\PlayableId;
use HomeFlix\Media\Domain\Model\PlayableKind;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use InvalidArgumentException;

final readonly class GetMediaOverviewHandler
{
    public function __construct(private PlayableCatalog $catalog, private MediaAssetRepository $assets) {}

    /**
     * @return MediaOverview|null null si le film ou l'épisode n'existe pas
     *
     * @throws InvalidArgumentException si l'identifiant n'est pas un UUID
     */
    public function handle(GetMediaOverview $query): ?MediaOverview
    {
        $id = PlayableId::fromString($query->targetId);
        $target = $query->kind === PlayableKind::Title ? PlayableRef::title($id) : PlayableRef::episode($id);
        $label = $this->catalog->label($target);

        if ($label === null) {
            return null;
        }

        $asset = $this->assets->findByTarget($target);

        return new MediaOverview(
            $query->kind,
            $query->targetId,
            $label,
            $asset?->status(),
            $asset?->errorMessage(),
            $asset?->duration()?->seconds,
            $asset === null ? [] : array_map(fn ($r) => $r->label, $asset->renditions()),
        );
    }
}
