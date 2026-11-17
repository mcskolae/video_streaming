<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $title_id
 * @property string|null $episode_id
 * @property string $source_path
 * @property string $status
 * @property int|null $duration_seconds
 * @property string|null $error_message
 * @property string|null $hls_master_path
 */
final class MediaAssetRecord extends Model
{
    protected $table = 'media_assets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['duration_seconds' => 'integer'];
    }

    /** @return HasMany<RenditionRecord, $this> */
    public function renditions(): HasMany
    {
        return $this->hasMany(RenditionRecord::class, 'media_asset_id')->orderBy('height');
    }
}
