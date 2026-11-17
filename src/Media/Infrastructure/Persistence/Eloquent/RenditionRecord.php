<?php

declare(strict_types=1);

namespace HomeFlix\Media\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $media_asset_id
 * @property string $label
 * @property int $width
 * @property int $height
 * @property int $video_bitrate_kbps
 * @property string $playlist_path
 */
final class RenditionRecord extends Model
{
    protected $table = 'renditions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'video_bitrate_kbps' => 'integer'];
    }
}
