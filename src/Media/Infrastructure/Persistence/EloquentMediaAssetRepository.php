<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Persistence;

use HomeFlix\Media\Domain\Model\Duration;
use HomeFlix\Media\Domain\Model\MediaAsset;
use HomeFlix\Media\Domain\Model\MediaAssetId;
use HomeFlix\Media\Domain\Model\MediaStatus;
use HomeFlix\Media\Domain\Model\PlayableId;
use HomeFlix\Media\Domain\Model\PlayableKind;
use HomeFlix\Media\Domain\Model\PlayableRef;
use HomeFlix\Media\Domain\Model\RelativePath;
use HomeFlix\Media\Domain\Model\Rendition;
use HomeFlix\Media\Domain\Repository\MediaAssetRepository;
use HomeFlix\Media\Infrastructure\Persistence\Eloquent\MediaAssetRecord;
use HomeFlix\Media\Infrastructure\Persistence\Eloquent\RenditionRecord;
use HomeFlix\Shared\Application\IdGenerator;
use Illuminate\Support\Facades\DB;

final readonly class EloquentMediaAssetRepository implements MediaAssetRepository
{
    public function __construct(private IdGenerator $ids) {}

    public function save(MediaAsset $asset): void
    {
        DB::transaction(function () use ($asset): void {
            $target = $asset->target();

            MediaAssetRecord::query()->updateOrCreate(['id' => $asset->id()->value], [
                'title_id' => $target->kind === PlayableKind::Title ? $target->id->value : null,
                'episode_id' => $target->kind === PlayableKind::Episode ? $target->id->value : null,
                'source_path' => $asset->sourcePath()->value,
                'status' => $asset->status()->value,
                'duration_seconds' => $asset->duration()?->seconds,
                'error_message' => $asset->errorMessage(),
                'hls_master_path' => $asset->hlsMasterPath()?->value,
            ]);

            $labels = [];

            foreach ($asset->renditions() as $rendition) {
                $labels[] = $rendition->label;
                $existing = RenditionRecord::query()
                    ->where('media_asset_id', $asset->id()->value)
                    ->where('label', $rendition->label)
                    ->first();

                RenditionRecord::query()->updateOrCreate(
                    ['id' => $existing->id ?? $this->ids->generate()],
                    [
                        'media_asset_id' => $asset->id()->value,
                        'label' => $rendition->label,
                        'width' => $rendition->width,
                        'height' => $rendition->height,
                        'video_bitrate_kbps' => $rendition->videoBitrateKbps,
                        'playlist_path' => $rendition->playlistPath->value,
                    ],
                );
            }

            RenditionRecord::query()->where('media_asset_id', $asset->id()->value)->whereNotIn('label', $labels)->delete();
        });
    }

    public function find(MediaAssetId $id): ?MediaAsset
    {
        return $this->toDomain(MediaAssetRecord::query()->with('renditions')->whereKey($id->value)->first());
    }

    public function findByTarget(PlayableRef $target): ?MediaAsset
    {
        $column = $target->kind === PlayableKind::Title ? 'title_id' : 'episode_id';

        return $this->toDomain(MediaAssetRecord::query()->with('renditions')->where($column, $target->id->value)->first());
    }

    private function toDomain(?MediaAssetRecord $record): ?MediaAsset
    {
        if ($record === null) {
            return null;
        }

        $target = $record->title_id !== null
            ? PlayableRef::title(PlayableId::fromString($record->title_id))
            : PlayableRef::episode(PlayableId::fromString((string) $record->episode_id));

        return MediaAsset::reconstitute(
            MediaAssetId::fromString($record->id),
            $target,
            new RelativePath($record->source_path),
            MediaStatus::from($record->status),
            $record->duration_seconds === null ? null : new Duration($record->duration_seconds),
            $record->error_message,
            $record->hls_master_path === null ? null : new RelativePath($record->hls_master_path),
            $record->renditions->map(fn (RenditionRecord $r) => new Rendition(
                $r->label, $r->width, $r->height, $r->video_bitrate_kbps, new RelativePath($r->playlist_path),
            ))->values()->all(),
        );
    }
}
