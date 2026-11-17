<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;

/** Copie les objets à l'entrée et à la sortie, et ne stocke pas les événements en attente, comme une vraie base. */
final class InMemoryMediaAssetRepository implements MediaAssetRepository
{
    /** @var array<string, string> */
    private array $stored = [];

    /** @var list<string> statuts successifs enregistrés, pour vérifier l'ordre des étapes */
    public array $statusHistory = [];

    public function save(MediaAsset $asset): void
    {
        $copy = $this->copy(serialize($asset));
        $copy->pullDomainEvents();

        $this->stored[$asset->id()->value] = serialize($copy);
        $this->statusHistory[] = $asset->status()->value;
    }

    public function find(MediaAssetId $id): ?MediaAsset
    {
        return isset($this->stored[$id->value]) ? $this->copy($this->stored[$id->value]) : null;
    }

    public function findByTarget(PlayableRef $target): ?MediaAsset
    {
        foreach ($this->stored as $data) {
            $asset = $this->copy($data);

            if ($asset->target()->equals($target)) {
                return $asset;
            }
        }

        return null;
    }

    public function delete(MediaAssetId $id): void
    {
        unset($this->stored[$id->value]);
    }

    private function copy(string $data): MediaAsset
    {
        $asset = unserialize($data);
        assert($asset instanceof MediaAsset);

        return $asset;
    }
}
