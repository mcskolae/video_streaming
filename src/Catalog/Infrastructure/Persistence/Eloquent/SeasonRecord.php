<?php

declare(strict_types=1);

namespace HomeFlix\Catalog\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $title_id
 * @property int $number
 * @property string $name
 * @property string $overview
 */
final class SeasonRecord extends Model
{
    protected $table = 'seasons';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['number' => 'integer'];
    }

    /** @return HasMany<EpisodeRecord, $this> */
    public function episodes(): HasMany
    {
        return $this->hasMany(EpisodeRecord::class, 'season_id')->orderBy('number');
    }
}
