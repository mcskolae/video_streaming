<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $season_id
 * @property int $number
 * @property string $name
 * @property string $overview
 * @property int|null $runtime_minutes
 * @property string|null $still_path
 * @property bool $is_playable
 */
final class EpisodeRecord extends Model
{
    protected $table = 'episodes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'runtime_minutes' => 'integer',
            'is_playable' => 'boolean',
        ];
    }
}
